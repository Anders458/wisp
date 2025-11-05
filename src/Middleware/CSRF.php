<?php

namespace Wisp\Middleware;

use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Wisp\Http\Request;
use Wisp\Http\Response;

class CSRF
{
   public function __construct (
      private SessionInterface $session,
      private Request $request,
      private Response $response,

      private string $header = 'X-CSRF-Token',
      private string $field  = 'wisp:csrf.token'
   )
   {
   }

   public function before ()
   {
      if ($this->request->headers->has ('Authorization')) {
         return;
      }

      if (!in_array ($this->request->getMethod (), [ 'POST', 'PUT', 'DELETE', 'PATCH' ])) {
         return;
      }

      $token = $this->request->headers->get ($this->header) ??
               $this->request->request->get ($this->field) ??
               '';

      if (!hash_equals ($this->getToken (), $token)) {
         return $this->response
            ->status (403)
            ->error ('Invalid CSRF token');
      }
   }

   public function getToken () : string
   {
      if (!$this->session->has ($this->field)) {
         $this->session->set ($this->field, bin2hex (random_bytes (32)));
      }

      return $this->session->get ($this->field);
   }
}
