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

   public function after ()
   {
      if ($this->session->isStarted ()) {
         $this->session->save ();
      }
   }

   public function before ()
   {
      if (!$this->session->isStarted ()) {
         $this->session->start ();
      }
   }
}
