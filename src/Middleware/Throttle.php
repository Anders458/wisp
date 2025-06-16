<?php

namespace Wisp\Middleware;

use Wisp\Http\Request;
use Wisp\Http\Response;

class Throttle
{
   private int $maxRequests;
   private int $period;
   
   public function __construct (array $settings = [])
   {
      $this->maxRequests = $settings ['requests'] ?? 60;
      $this->period = $settings ['period'] ?? 60;
   }
   
   public function before (Request $request, Response $response) : void
   {
      if (!function_exists ('apcu_fetch')) {
         throw new \Exception ('APCu extension is required to use the throttle middleware');
      }
      
      $key = 'throttle_' . $request->ip ();
      $now = time ();
      
      $data = apcu_fetch ($key) ?: [
         'count' => 0,
         'reset' => $now + $this->period
      ];

      if ($now > $data ['reset']) {
         $data = [
            'count' => 0,
            'reset' => $now + $this->period
         ];
      }
      
      $data ['count']++;

      apcu_store ($key, $data, $this->period);
      
      $response->headers ['X-RateLimit-Limit'] = $this->maxRequests;
      $response->headers ['X-RateLimit-Remaining'] = max (0, $this->maxRequests - $data ['count']);
      $response->headers ['X-RateLimit-Reset'] = $data ['reset'];
      
      if ($data ['count'] > $this->maxRequests) {
         $response->headers ['Retry-After'] = $data ['reset'] - $now;

         $response
            ->status (429);
      }
   }
}