<?php

namespace Wisp\Middleware;

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Wisp\Http\Request;

class Session
{
   private string $cookieName = 'wisp:session';
   private int $cookieLifetime = 604800;
   private bool $secure = true;
   private string $sameSite = Cookie::SAMESITE_LAX;

   public function __construct (
      private SessionInterface $session,
      private Request $request,
      private SymfonyResponse $response,
      ?string $cookieName = null,
      ?int $cookieLifetime = null,
      ?bool $secure = null,
      ?string $sameSite = null
   )
   {
      if ($cookieName !== null) {
         $this->cookieName = $cookieName;
      }

      if ($cookieLifetime !== null) {
         $this->cookieLifetime = $cookieLifetime;
      }
      
      if ($secure !== null) {
         $this->secure = $secure;
      }
      
      if ($sameSite !== null) {
         $this->sameSite = $sameSite;
      }
   }

   public function before () : void
   {
      $sessionId = $this->request->cookies->get ($this->cookieName);

      if ($sessionId) {
         $this->session->setId ($sessionId);
      }

      if (!$this->session->isStarted ()) {
         $this->session->start ();
      }

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

   public function after () : void
   {
      if ($this->session->isStarted ()) {
         $this->session->save ();

         $cookie = Cookie::create ($this->cookieName)
            ->withValue ($this->session->getId ())
            ->withExpires (time () + $this->cookieLifetime)
            ->withPath ('/')
            ->withSecure ($this->secure)
            ->withHttpOnly (true)
            ->withSameSite ($this->sameSite);

         $this->response->headers->setCookie ($cookie);
      }
   }
}
