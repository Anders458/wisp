<?php

namespace Wisp\Example\Controller;

use Psr\Log\LoggerInterface;
use Wisp\Http\Request;
use Wisp\Http\Response;

class ErrorController
{
   public function __construct (
      protected Request $request,
      protected Response $response,
      protected LoggerInterface $logger
   )
   {
   }

   public function internalError ()
   {
      $this->logger->error ('Internal server error occurred', [
         'uri' => $this->request->getRequestUri (),
         'method' => $this->request->getMethod ()
      ]);

      return $this->response
         ->status (500)
         ->json ([
            'error' => 'Internal Server Error',
            'message' => 'An unexpected error occurred'
         ]);
   }

   public function notFound ()
   {
      $this->logger->warning ('Resource not found', [
         'uri' => $this->request->getRequestUri (),
         'method' => $this->request->getMethod ()
      ]);

      return $this->response
         ->status (404)
         ->json ([
            'error' => 'Not Found',
            'message' => 'The requested resource was not found'
         ]);
   }
}
