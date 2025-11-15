<?php

namespace Wisp\Middleware;

use Psr\Cache\CacheItemPoolInterface;
use Wisp\Http\Request;
use Wisp\Http\Response;

class RateLimiter
{
   public function __construct (
      private CacheItemPoolInterface $cache,
      private Request $request,
      private Response $response,

      private int $limit  = 60,
      private int $interval = 60,
      private string $strategy = 'fixed_window'
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
            $this->response->headers->set ('Retry-After', (string) $this->interval);

            return $this->response
               ->status (429)
               ->json ([
                  'error' => 'Too Many Requests',
                  'message' => 'Rate limit exceeded. Please try again later.',
                  'retry_after' => $this->interval
               ]);
         }

         $item->set ($requests + 1);
      } else {
         $item->set (1);
         $item->expiresAfter ($this->interval);
      }

      $this->cache->save ($item);

      $remaining = max (0, $this->limit - ($item->get () - 1));
      $this->response->headers->set ('X-RateLimit-Limit', (string) $this->limit);
      $this->response->headers->set ('X-RateLimit-Remaining', (string) $remaining);
   }
}
