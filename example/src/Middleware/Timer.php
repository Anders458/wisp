<?php

namespace Wisp\Example\Middleware;

use Psr\Log\LoggerInterface;

class Timer
{
   private float $startTime;

   public function __construct (
      private LoggerInterface $logger,
      private string $label = 'Request'
   )
   {
   }

   public function before ()
   {
      $this->startTime = microtime (true);
      $this->logger->debug ('Middleware: Timer before');
   }

   public function after ()
   {
      $duration = round ((microtime (true) - $this->startTime) * 1000, 2);

      $this->logger->debug ('Middleware: Timer after');
   }
}
