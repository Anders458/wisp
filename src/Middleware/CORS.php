<?php

namespace Wisp\Middleware;

use Exception;
use Wisp\Http\Request;
use Wisp\Http\Response;

class CORS
{
   private array $options;

   public function __construct (array $options = [])
   {
      $this->options = array_merge ([
         'origins' => [ '*' ],
         'methods' => [ 'GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS' ],
         'headers' => [ 'Content-Type', 'Authorization' ],
         'exposed_headers' => [],
         'max_age' => 3600,
         'credentials' => true,
         'private_network' => false
      ], $options);
      
      if (!is_array ($options ['origins'])) {
         throw new Exception ('CORS middleware configuration requires a list of origins ([ \'origins\' => [ \'example1.com\', \'example2.com\', ... ], ... ])');
      }
   }

   public function before (Request $request, Response $response)
   {
      $origin = $request->origin ();
      
      if (!in_array ($origin, $this->options ['origins']) &&
          !in_array ('*', $this->options ['origins'])) {
         return;
      }

      $response->headers ['Access-Control-Allow-Origin'] = $origin;

      if ($this->options ['credentials']) {
         $response->headers ['Access-Control-Allow-Credentials'] = 'true';
      }

      if (!empty ($this->options ['exposed_headers'])) {
         $response->headers ['Access-Control-Expose-Headers'] = implode (', ', $this->options ['exposed_headers']);  
      }

      if ($request->method === 'OPTIONS') {
         $response->headers ['Access-Control-Allow-Methods'] = implode (', ', $this->options ['methods']);

         if ($this->options ['headers']) {
            $response->headers ['Access-Control-Allow-Headers'] = implode (', ', $this->options ['headers']);
         }

         if ($this->options ['max_age'] > 0) {
            $response->headers ['Access-Control-Max-Age'] = $this->options ['max_age'];
         }

         if ($this->options ['private_network'] && $request->headers ['Access-Control-Request-Private-Network']) {
            $response->headers ['Access-Control-Allow-Private-Network'] = 'true';
         }

         $response
            ->status (204)
            ->send ();
      }
   }
}