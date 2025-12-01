<?php

namespace Wisp;

use Symfony\Component\Console\Input\ArgvInput;

class Runtime
{
   private float $startTime;

   public function __construct (
      private Stage $stage = Stage::Production,
      private bool $debug = false,
      private string $version = '1.0.0'
   )
   {
      $this->startTime = microtime (true);
   }

   public static function configure (): RuntimeBuilder
   {
      return new RuntimeBuilder ();
   }

   public function elapsed (): float
   {
      return microtime (true) - $this->startTime;
   }

   public function stage (): Stage
   {
      return $this->stage;
   }

   public function version (): string
   {
      return $this->version;
   }

   public function is (Stage $stage): bool
   {
      return $this->stage === $stage;
   }

   public function isCli (): bool
   {
      return PHP_SAPI === 'cli';
   }

   public function isDebug (): bool
   {
      return $this->debug;
   }

   public function isDevelopment (): bool
   {
      return $this->stage === Stage::Development;
   }

   public function isStaging (): bool
   {
      return $this->stage === Stage::Staging;
   }

   public function isProduction (): bool
   {
      return $this->stage === Stage::Production;
   }
}
