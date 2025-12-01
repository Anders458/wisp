<?php

namespace Wisp\Environment;

class RuntimeBuilder
{
   private string $root = '';
   private string $version = '1.0.0';
   private Stage $defaultStage = Stage::production;
   private bool $defaultDebug = false;

   private bool $detectStageFromCli = false;
   private bool $detectDebugFromCli = false;
   private array $hostnameMap = [];
   private ?string $debugQuerySecret = null;
   private bool $debugQueryEnabled = false;
   private array $debugAllowedStages = [];

   public function root (string $path) : self
   {
      $this->root = $path;
      return $this;
   }

   public function version (string $version) : self
   {
      $this->version = $version;
      return $this;
   }

   public function stage (Stage $default) : self
   {
      $this->defaultStage = $default;
      return $this;
   }

   public function debug (bool $default) : self
   {
      $this->defaultDebug = $default;
      return $this;
   }

   public function detectStageFromCli () : self
   {
      $this->detectStageFromCli = true;
      return $this;
   }

   public function detectDebugFromCli () : self
   {
      $this->detectDebugFromCli = true;
      return $this;
   }

   public function detectStageFromHostname (array $map) : self
   {
      $this->hostnameMap = $map;
      return $this;
   }

   public function allowDebugFromQuery (?string $secret = null) : self
   {
      $this->debugQueryEnabled = true;
      $this->debugQuerySecret = $secret;
      return $this;
   }

   public function allowDebugInStages (array $stages) : self
   {
      $this->debugAllowedStages = $stages;
      return $this;
   }

   public function build () : Runtime
   {
      $stage = $this->resolveStage ();
      $debug = $this->resolveDebug ($stage);

      return new Runtime (
         $this->root,
         $debug,
         $stage,
         $this->version
      );
   }

   private function resolveStage () : Stage
   {
      if ($this->detectStageFromCli && PHP_SAPI === 'cli') {
         $cliStage = $this->parseStageFromCli ();

         if ($cliStage !== null) {
            return $cliStage;
         }
      }

      if (!empty ($this->hostnameMap)) {
         $hostname = $_SERVER ['HTTP_HOST'] ?? $_SERVER ['SERVER_NAME'] ?? 'localhost';
         $hostStage = $this->matchHostname ($hostname);

         if ($hostStage !== null) {
            return $hostStage;
         }
      }

      return $this->defaultStage;
   }

   private function resolveDebug (Stage $stage) : bool
   {
      if ($this->detectDebugFromCli && PHP_SAPI === 'cli') {
         $cliDebug = $this->parseDebugFromCli ();

         if ($cliDebug) {
            return $this->isDebugAllowedInStage ($stage) ? true : $this->defaultDebug;
         }
      }

      if ($this->debugQueryEnabled && PHP_SAPI !== 'cli') {
         $queryDebug = $this->parseDebugFromQuery ();

         if ($queryDebug && $this->isDebugAllowedInStage ($stage)) {
            return true;
         }
      }

      return $this->defaultDebug;
   }

   private function parseStageFromCli () : ?Stage
   {
      $argv = $_SERVER ['argv'] ?? [];

      foreach ($argv as $arg) {
         if (preg_match ('/^--stage=(.+)$/', $arg, $matches)) {
            return Stage::tryFrom ($matches [1]);
         }

         if (preg_match ('/^-s(.+)$/', $arg, $matches)) {
            return Stage::tryFrom ($matches [1]);
         }
      }

      for ($i = 0; $i < count ($argv) - 1; $i++) {
         if ($argv [$i] === '--stage' || $argv [$i] === '-s') {
            return Stage::tryFrom ($argv [$i + 1]);
         }
      }

      return null;
   }

   private function parseDebugFromCli () : bool
   {
      $argv = $_SERVER ['argv'] ?? [];

      foreach ($argv as $arg) {
         if ($arg === '--debug' || $arg === '-d') {
            return true;
         }
      }

      return false;
   }

   private function parseDebugFromQuery () : bool
   {
      $debugParam = $_GET ['debug'] ?? null;

      if ($debugParam === null) {
         return false;
      }

      if ($this->debugQuerySecret !== null) {
         return hash_equals ($this->debugQuerySecret, (string) $debugParam);
      }

      return (bool) $debugParam;
   }

   private function matchHostname (string $hostname) : ?Stage
   {
      $hostname = strtolower ($hostname);

      if (str_contains ($hostname, ':')) {
         $hostname = explode (':', $hostname) [0];
      }

      if (isset ($this->hostnameMap [$hostname])) {
         return $this->hostnameMap [$hostname];
      }

      foreach ($this->hostnameMap as $pattern => $stage) {
         if ($this->matchWildcard ($pattern, $hostname)) {
            return $stage;
         }
      }

      return null;
   }

   private function matchWildcard (string $pattern, string $hostname) : bool
   {
      $pattern = strtolower ($pattern);

      if (!str_contains ($pattern, '*')) {
         return $pattern === $hostname;
      }

      $regex = '/^' . str_replace (
         [ '.', '*' ],
         [ '\\.', '[^.]+' ],
         $pattern
      ) . '$/';

      return preg_match ($regex, $hostname) === 1;
   }

   private function isDebugAllowedInStage (Stage $stage) : bool
   {
      if (empty ($this->debugAllowedStages)) {
         return true;
      }

      return in_array ($stage, $this->debugAllowedStages, true);
   }
}
