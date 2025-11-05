<?php

namespace Wisp\Middleware;

use Symfony\Component\HttpFoundation\Session\SessionInterface;

class Session
{
   public function __construct (
      private SessionInterface $session
   )
   {
   }

   public function before () : void
   {
      if (!$this->session->isStarted ()) {
         $this->session->start ();
      }
   }

   public function after () : void
   {
      if ($this->session->isStarted ()) {
         $this->session->save ();
      }
   }
}
