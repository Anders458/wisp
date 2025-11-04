<?php

namespace Wisp\Middleware;

use Closure;
use Psr\Cache\CacheItemPoolInterface;
use Wisp\Http\Request;
use Wisp\Http\Response;

class Throttle
{
   public function __construct (
      private CacheItemPoolInterface $cache,
      private Request $request,
      private Response $response,
      private int $maxAttempts = 60,
      private int $decaySeconds = 60,
      private ?Closure $keyResolver = null
   )
   {
   }

   public function before ()
   {
      $key = $this->getKey ();
      $cacheKey = 'rate_limit_' . md5 ($key);

      $item = $this->cache->getItem ($cacheKey);

      if ($item->isHit ()) {
         $attempts = $item->get ();

         if ($attempts >= $this->maxAttempts) {
            $this->response->headers->set ('X-RateLimit-Limit', (string) $this->maxAttempts);
            $this->response->headers->set ('X-RateLimit-Remaining', '0');

            return $this->response
               ->status (429)
               ->json ([ 'error' => 'Too many requests' ]);
         }

         $item->set ($attempts + 1);
      } else {
         $item->set (1);
         $item->expiresAfter ($this->decaySeconds);
      }

      $this->cache->save ($item);
   }

   private function getKey () : string
   {
      if ($this->keyResolver !== null) {
         return ($this->keyResolver) ($this->request);
      }

      return $this->request->getClientIp () ?? 'unknown';
   }
}
