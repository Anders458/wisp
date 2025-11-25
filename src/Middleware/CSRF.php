<?php

namespace Wisp\Middleware;

use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
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
      private TokenStorageInterface $tokenStorage,
      private CsrfTokenManagerInterface $csrfTokenManager,
      private string $tokenId = 'wisp:csrf',
      private string $header = 'X-CSRF-Token',
      private string $field = 'wisp:csrf.token'
   )
   {
   }

   public function before () : ?Response
   {
      if ($this->tokenStorage->getToken () !== null) {
         return null;
      }

      if (!in_array ($this->request->getMethod (), [ 'POST', 'PUT', 'DELETE', 'PATCH' ])) {
         return null;
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

      return null;
   }

   public function getToken () : string
   {
      return $this->csrfTokenManager->getToken ($this->tokenId)->getValue ();
   }
}
