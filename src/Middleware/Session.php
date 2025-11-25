<?php

namespace Wisp\Middleware;

use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Wisp\Http\Request;

class Session
{
   public function __construct (
      private SessionInterface $session,
      private Request $request
   )
   {
   }

   public function before () : void
   {
      // Start session - PHP automatically reads session ID from cookie
      // and sends Set-Cookie header via NativeSessionStorage
      if (!$this->session->isStarted ()) {
         $this->session->start ();
      }

      // Make session available to request
      $this->request->setSession ($this->session);
   }

   /**
    * Regenerate session ID to prevent session fixation attacks.
    * Call this method after successful login or privilege escalation.
    *
    * @param bool $deleteOldSession Whether to delete the old session data
    * @return void
    */
   public function regenerate (bool $deleteOldSession = true) : void
   {
      $this->session->migrate ($deleteOldSession);
   }
}
