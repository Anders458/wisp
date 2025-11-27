<?php

namespace Wisp\Middleware\Authentication;

use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface as CurrentUserStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken as UserAuthContext;
use Wisp\Contracts\UserProviderInterface;

class CookieAuthentication
{
   public function __construct (
      private SessionInterface $session,
      private CurrentUserStorageInterface $currentUserStorage,
      private UserProviderInterface $userProvider
   )
   {
   }

   public function before () : void
   {
      if (!$this->session->isStarted ()) {
         throw new \LogicException (
            'CookieAuthentication middleware requires Session middleware to be registered. ' .
            'Add Session::class to your middleware stack before using CookieAuthentication.'
         );
      }

      // Check if user is authenticated in session
      if (!$this->session->has ('user_id')) {
         return;
      }

      $userId = $this->session->get ('user_id');

      // Load user via provider
      $user = $this->userProvider->loadUser ($userId);

      if (!$user) {
         // User not found - clear stale session data
         $this->session->remove ('user_id');
         return;
      }

      // Create authentication token and store it
      $authContext = new UserAuthContext ($user, 'main', $user->getRoles ());
      $this->currentUserStorage->setToken ($authContext);
   }
}
