<?php

namespace Wisp\Example\Controller;

use Psr\Log\LoggerInterface;
use Wisp\Http\Request;
use Wisp\Http\Response;

class SystemController
{
   public function __construct (
      protected Request $request,
      protected Response $response,
      protected LoggerInterface $logger
   )
   {
   }

   public function healthCheck ()
   {
      $this->logger->info (__ ('health.check_accessed'));

      return $this->response
         ->status (200)
         ->json ([
            'status' => __ ('system.status'),
            'timestamp' => time ()
         ]);
   }
}
