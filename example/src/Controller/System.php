<?php

namespace Wisp\Example\Controller;

use Psr\Log\LoggerInterface;
use Wisp\Http\Request;
use Wisp\Http\Response;

class System
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
      $this->logger->info ('Health check endpoint accessed');

      return $this->response
         ->status (200)
         ->json ([
            'status' => 'ok',
            'timestamp' => time ()
         ]);
   }
}