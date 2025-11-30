<?php

namespace Wisp\Middleware\Authentication;

use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface as CurrentUserStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken as UserAuthContext;
use Symfony\Component\Security\Core\User\UserInterface;
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

      if (!$this->session->has ('user_id')) {
         return;
      }

      $userId = $this->session->get ('user_id');

      $user = $this->userProvider->loadUser ($userId);

      if (!$user) {
         $this->session->remove ('user_id');
         return;
      }

      $authContext = new UserAuthContext ($user, 'main', $user->getRoles ());
      $this->currentUserStorage->setToken ($authContext);
   }

   public function login (UserInterface $user) : void
   {
      if (!$this->session->isStarted ()) {
         throw new \LogicException (
            'CookieAuthentication requires an active session. ' .
            'Ensure Session middleware is registered and session is started.'
         );
      }

      $this->session->migrate (true);

      $this->session->set ('user_id', $user->getUserIdentifier ());

      $authContext = new UserAuthContext ($user, 'main', $user->getRoles ());
      $this->currentUserStorage->setToken ($authContext);
   }

   public function logout () : void
   {
      $this->session->remove ('user_id');
      $this->session->invalidate ();
      $this->currentUserStorage->setToken (null);
   }
}
