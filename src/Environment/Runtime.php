<?php

namespace Wisp\Environment;

class Runtime implements RuntimeInterface
{
   private float  $startTime;
   private string $root;
   private bool   $debug;
   private Stage  $stage;
   private string $version;

   public static function configure () : RuntimeBuilder
   {
      return new RuntimeBuilder ();
   }

   public function __construct (
      string $root,
      bool $debug = false,
      Stage $stage = Stage::production,
      string $version = '1.0.0'
   ) 
   {
      $this->startTime = microtime (true);
      $this->root = $root;
      $this->debug = $debug;
      $this->stage = $stage;
      $this->version = $version;
   }
   
   public function getElapsedTime (): float
   {
      return microtime (true) - $this->startTime;
   }
   
   public function getStage (): Stage
   {
      return $this->stage;
   }
   
   public function getVersion (): string
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

   public function getRoot (): string
   {
      return $this->root;
   }
}