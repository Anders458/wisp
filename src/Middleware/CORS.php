<?php

namespace Wisp\Middleware;

use Wisp\Http\Request;
use Wisp\Http\Response;

class CORS
{
   public function __construct (
      private Response $response,
      private array $config = []
   )
   {
      $this->config = array_merge ([
         'origins' => [ '*' ],
         'methods' => [ 'GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS' ],
         'headers' => [ 'Content-Type', 'Authorization', 'X-Requested-With' ],
         'expose_headers' => [],
         'credentials' => false,
         'max_age' => 86400
      ], $this->config);

      if (in_array ('*', $this->config ['origins']) && $this->config ['credentials']) {
         throw new \InvalidArgumentException ('Cannot use wildcard origin (*) with credentials enabled. Either specify explicit origins or disable credentials.');
      }
   }

   public function before (Request $request)
   {
      $origin = $request->server->get ('HTTP_ORIGIN', '');

      if ($this->isOriginAllowed ($origin)) {
         $this->response->headers->set ('Access-Control-Allow-Origin', $origin);

         if ($this->config ['credentials']) {
            $this->response->headers->set ('Access-Control-Allow-Credentials', 'true');
         }

         if (!empty ($this->config ['expose_headers'])) {
            $this->response->headers->set ('Access-Control-Expose-Headers', implode (', ', $this->config ['expose_headers']));
         }

         if ($request->getMethod () === 'OPTIONS') {
            $this->response->headers->set ('Access-Control-Allow-Methods', implode (', ', $this->config ['methods']));
            $this->response->headers->set ('Access-Control-Allow-Headers', implode (', ', $this->config ['headers']));
            $this->response->headers->set ('Access-Control-Max-Age', (string) $this->config ['max_age']);

            return $this->response->status (204);
         }
      }
   }

   private function isOriginAllowed (string $origin) : bool
   {
      if (empty ($origin)) {
         return false;
      }

      if (in_array ('*', $this->config ['origins'])) {
         return true;
      }

      return in_array ($origin, $this->config ['origins']);
   }
}
