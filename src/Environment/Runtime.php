<?php

namespace Wisp\Environment;

class Runtime
{
   private $startTime;
   private $debug;
   private $version;
   private $stage;
   
   public function __construct (array $settings)
   {
      $this->startTime = microtime (true);

      $this->debug   = $settings ['debug'];
      $this->version = $settings ['version'];
      $this->stage   = $settings ['stage'];

      if ($this->debug) {
         error_reporting (E_ALL);
         ini_set ('display_errors', 1);
      } else {
         error_reporting (~E_ALL);
         ini_set ('display_errors', 0);
      }
   }

   public function elapsed () : float
   {
      return microtime (true) - $this->startTime;
   }

   public function getStage () : Stage
   {
      return $this->stage;
   }

   public function getVersion () : string
   {
      return $this->version;
   }

   public function is (Stage $stage) : bool
   {
      return $this->stage === $stage;
   }

   public function isCli () : bool
   {
      return php_sapi_name () === 'cli';
   }

   public function isDebug () : bool
   {
      return $this->debug;
   }
}