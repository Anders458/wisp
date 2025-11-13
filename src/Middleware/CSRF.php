<?php

namespace Wisp\Middleware;

use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Wisp\Http\Request;
use Wisp\Http\Response;

class CSRF
{
   public function __construct (
      private Request $request,
      private Response $response,
      private SessionInterface $session,
      private CsrfTokenManagerInterface $csrfTokenManager,
      private string $tokenId = 'wisp:csrf',
      private string $header = 'X-CSRF-Token',
      private string $field = 'wisp:csrf.token'
   )
   {
   }

   public function before ()
   {
      if ($this->request->headers->has ('Authorization')) {
         return;
      }

      if (!in_array ($this->request->getMethod (), ['POST', 'PUT', 'DELETE', 'PATCH'])) {
         return;
      }

      $token = $this->request->headers->get ($this->header)
            ?? $this->request->request->get ($this->field)
            ?? '';

      if (!$token) {
         return $this->response
            ->status (403)
            ->error ('CSRF token missing');
      }

      $csrfToken = new CsrfToken ($this->tokenId, $token);

      if (!$this->csrfTokenManager->isTokenValid ($csrfToken)) {
         return $this->response
            ->status (403)
            ->error ('Invalid CSRF token');
      }
   }

   public function getToken () : string
   {
      // Get or generate CSRF token using Symfony
      return $this->csrfTokenManager->getToken ($this->tokenId)->getValue ();
   }
}
