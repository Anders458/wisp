<?php

namespace Wisp\Middleware;

use Wisp\Http\Response;

class Helmet
{
   public function __construct (
      private Response $response,
      private string $csp = "default-src 'self'",
      private int $hstsMaxAge = 31536000,
      private string $frameOptions = 'DENY',
      private string $referrerPolicy = 'no-referrer-when-downgrade'
   )
   {
   }

   public function before ()
   {
      // Security headers
      $this->response->headers->set ('X-Frame-Options', $this->frameOptions);
      $this->response->headers->set ('X-Content-Type-Options', 'nosniff');
      $this->response->headers->set ('X-XSS-Protection', '1; mode=block');
      $this->response->headers->set ('Referrer-Policy', $this->referrerPolicy);

      // Content Security Policy
      if (!empty ($this->csp)) {
         $this->response->headers->set ('Content-Security-Policy', $this->csp);
      }

      // HSTS (only if max age > 0)
      if ($this->hstsMaxAge > 0) {
         $this->response->headers->set (
            'Strict-Transport-Security',
            'max-age=' . $this->hstsMaxAge . '; includeSubDomains'
         );
      }
   }
}
