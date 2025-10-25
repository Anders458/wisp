<?php

namespace Wisp\Example\Middleware;

use Psr\Log\LoggerInterface;
use Wisp\Http\Request;

class RateLimit
{
   public function __construct (
      private Request $request,
      private LoggerInterface $logger,
      private int $maxRequests = 100
   )
   {
   }

   public function before ()
   {
      $ip = $this->request->getClientIp ();

      $this->logger->debug ('Middleware: RateLimit before');
   }
}
