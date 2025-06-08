<?php

namespace Wisp\Middleware;

use Wisp\Http\Request;
use Wisp\Http\Response;

class Session
{
   public function __construct (array $options = [])
   {
      session_set_cookie_params (
         [
            'lifetime' => $options ['lifetime'] ?? 0,
            'path'     => $options ['path']     ?? '',
            'domain'   => $options ['domain']   ?? '',
            'secure'   => $options ['secure']   ?? false,
            'httponly' => $options ['httponly'] ?? false,
            'samesite' => $options ['samesite'] ?? 'Lax'
         ]
      );

      session_start ();
   }

   public function before (Request $request, Response $response)
   {
   }

   public function after (Request $request, Response $response)
   {
   }
}