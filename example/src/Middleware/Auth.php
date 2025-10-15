<?php

namespace Wisp\Example\Middleware;

use Wisp\Http\Request;
use Wisp\Http\Response;

class Auth
{
   public function __construct (
      private Request  $request,
      private Response $response
   )
   {
   }

   public function before ()
   {
      $token = $this->request->headers->get ('Authorization');

      // if (!$token) {
      //    return $this->response
      //       ->status (401)
      //       ->json ([
      //          'error' => 'Unauthorized',
      //          'message' => 'Missing Authorization header'
      //       ]);
      // }

      // // Example: validate token format
      // if (!str_starts_with ($token, 'Bearer ')) {
      //    return $this->response
      //       ->status (401)
      //       ->json ([
      //          'error' => 'Unauthorized',
      //          'message' => 'Invalid Authorization header format'
      //       ]);
      // }
   }
}
