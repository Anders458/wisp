<?php

namespace Wisp\Service;

use Symfony\Component\Yaml\Yaml;
use Wisp\Environment\RuntimeInterface;

class Keychain implements KeychainInterface
{
   private array $configs = [];

   public function __construct (
      private string $path,
      private RuntimeInterface $runtime
   )
   {
      $this->load ();
   }

   private function load () : void
   {
      if (!is_dir ($this->path)) {
         return;
      }

      $files = glob ($this->path . '/*.yaml');

      if (!$files) {
         return;
      }

      // Group files by base name
      $baseFiles = [];
      $envFiles = [];

      foreach ($files as $file) {
         $basename = basename ($file, '.yaml');

         // Check if this is an environment-specific file
         $stage = $this->runtime->getStage ();
         $envSuffix = '.' . $stage->value;

         if (str_ends_with ($basename, $envSuffix)) {
            // Extract base name (e.g., 'config.dev' -> 'config')
            $configName = substr ($basename, 0, -strlen ($envSuffix));
            $envFiles [$configName] = $file;
         } else {
            // This is a base file
            $baseFiles [$basename] = $file;
         }
      }

      // Load and merge configs
      foreach ($baseFiles as $name => $baseFile) {
         $config = Yaml::parseFile ($baseFile) ?? [];

         // Check for environment-specific override
         if (isset ($envFiles [$name])) {
            $envConfig = Yaml::parseFile ($envFiles [$name]) ?? [];
            $config = $this->mergeRecursive ($config, $envConfig);
         }

         $this->configs [$name] = $config;
      }

      // Handle environment files without base files
      foreach ($envFiles as $name => $envFile) {
         if (!isset ($this->configs [$name])) {
            $this->configs [$name] = Yaml::parseFile ($envFile) ?? [];
         }
      }
   }

   public function get (string $name) : ?array
   {
      return $this->configs [$name] ?? null;
   }

   private function mergeRecursive (array $base, array $override) : array
   {
      foreach ($override as $key => $value) {
         if (is_array ($value) && isset ($base [$key]) && is_array ($base [$key])) {
            $base [$key] = $this->mergeRecursive ($base [$key], $value);
         } else {
            $base [$key] = $value;
         }
      }

      return $base;
   }
}
