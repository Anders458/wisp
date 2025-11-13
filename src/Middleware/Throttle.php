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

      private int $limit  = 60,
      private int $window = 10
   )
   {
   }

   public function before ()
   {
      $cacheKey = 'wisp:rate_limit_' . md5 ($this->request->getClientIp ());

      $item = $this->cache->getItem ($cacheKey);

      if ($item->isHit ()) {
         $requests = $item->get ();

         if ($requests >= $this->limit) {
            $this->response->headers->set ('X-RateLimit-Limit', (string) $this->limit);
            $this->response->headers->set ('X-RateLimit-Remaining', '0');

            return $this->response
               ->status (429);
         }

         $item->set ($requests + 1);
      } else {
         $item->set (1);
         $item->expiresAfter ($this->window);
      }

      $this->cache->save ($item);
   }
}
