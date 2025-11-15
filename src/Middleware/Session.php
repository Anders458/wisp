<?php

namespace Wisp\Middleware;

use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Wisp\Http\Request;

/**
 * Session Middleware
 *
 * Starts the PHP session and makes it available to the request.
 * Session configuration (cookie name, security settings, storage) is handled
 * by CacheSessionStorage which is registered in the DI container.
 *
 * COOKIE HANDLING:
 * Session cookies are automatically managed by PHP's NativeSessionStorage.
 * The session ID is read from $_COOKIE and the Set-Cookie header is sent
 * automatically when the session is started or modified. No manual cookie
 * handling is needed in this middleware.
 */
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
