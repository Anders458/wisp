<?php

namespace Wisp\Example\Controller;

use Psr\Log\LoggerInterface;
use Wisp\Http\Request;
use Wisp\Http\Response;

class SystemController
{
   public function __construct (
      protected LoggerInterface $logger
   )
   {
   }

   public function healthCheck (Request $request, Response $response)
   {
      $this->logger->info (__ ('health.check_accessed'));

      return $response
         ->status (200)
         ->json ([
            'status' => __ ('system.status'),
            'timestamp' => time ()
         ]);
   }
}
