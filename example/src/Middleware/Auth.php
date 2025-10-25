<?php

namespace Wisp\Example\Middleware;

use Psr\Log\LoggerInterface;
use Wisp\Http\Request;
use Wisp\Http\Response;

class Auth
{
   public function __construct (
      private Request  $request,
      private Response $response,
      private LoggerInterface $logger
   )
   {
   }

   public function before ()
   {
      $token = $this->request->headers->get ('Authorization');

      $this->logger->debug ('Middleware: Auth before');

      // Example auth validation (currently disabled for demo purposes)
      // Uncomment to enable token validation

      // if (!$token) {
      //    $this->logger->warning ('Auth failed: Missing authorization header');
      //    return $this->response
      //       ->status (401)
      //       ->body ([
      //          'error' => 'Unauthorized',
      //          'message' => 'Missing Authorization header'
      //       ]);
      // }

      // if (!str_starts_with ($token, 'Bearer ')) {
      //    $this->logger->warning ('Auth failed: Invalid token format');
      //    return $this->response
      //       ->status (401)
      //       ->body ([
      //          'error' => 'Unauthorized',
      //          'message' => 'Invalid Authorization header format'
      //       ]);
      // }
   }
}
