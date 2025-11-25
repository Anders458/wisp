<?php

namespace Wisp\Middleware;

use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class Helmet
{
   public function __construct (
      private SymfonyResponse $response,

      private string $csp = "default-src 'self'",

      private int $hstsMaxAge = 31536000,

      private string $frameOptions = 'DENY',
      private string $referrerPolicy = 'no-referrer-when-downgrade'
   )
   {
   }

   public function before () : void
   {
      $this->response->headers->set ('X-Frame-Options', $this->frameOptions);
      $this->response->headers->set ('X-Content-Type-Options', 'nosniff');
      $this->response->headers->set ('X-XSS-Protection', '1; mode=block');
      $this->response->headers->set ('Referrer-Policy', $this->referrerPolicy);

      if (!empty ($this->csp)) {
         $this->response->headers->set ('Content-Security-Policy', $this->csp);
      }

      if ($this->hstsMaxAge > 0) {
         $this->response->headers->set (
            'Strict-Transport-Security',
            'max-age=' . $this->hstsMaxAge . '; includeSubDomains'
         );
      }
   }
}
