<?php

namespace Wisp\Middleware\Authentication;

use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface as CurrentUserStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken as AuthenticatedToken;
use Wisp\Http\Request;
use Wisp\Security\Contracts\UserProviderInterface;
use Wisp\Security\UserProvider\CacheUserProvider;
use Wisp\Security\UserProvider\ClosureUserProvider;

class CookieAuthentication
{
   private UserProviderInterface $userProvider;

   public function __construct (
      private Request $request,
      private SessionInterface $session,
      private CurrentUserStorageInterface $tokenStorage,
      ?UserProviderInterface $userProviderInstance = null,
      private mixed $user_provider = null,
      private string $name = 'wisp_session',
      private int $ttl = 86400,
      private bool $secure = true,
      private bool $httponly = true,
      private string $samesite = 'Lax',
      private ?int $remember_ttl = null
   )
   {
      // Resolve user provider
      $this->userProvider = $this->resolveUserProvider ($userProviderInstance);
   }

   public function before ()
   {
      // Start session if not already started
      if (!$this->session->isStarted ()) {
         $this->session->start ();
      }

      // Check if user is authenticated in session
      if (!$this->session->has ('user_id')) {
         return;
      }

      $userId = $this->session->get ('user_id');

      // Load user via provider
      $user = $this->userProvider->loadUser ($userId);

      if (!$user) {
         // User not found - clear session
         $this->session->remove ('user_id');
         return;
      }

      // Create authentication token and store it in current user storage
      $token = new AuthenticatedToken ($user, 'main', $user->getRoles ());
      $this->tokenStorage->setToken ($token);
   }

   public function after () : void
   {
      if ($this->session->isStarted ()) {
         $this->session->save ();
      }
   }

   private function resolveUserProvider (?UserProviderInterface $instance) : UserProviderInterface
   {
      // Container-registered provider takes precedence
      if ($instance !== null) {
         return $instance;
      }

      // Fallback to user_provider setting
      if ($this->user_provider instanceof UserProviderInterface) {
         return $this->user_provider;
      }

      if ($this->user_provider instanceof \Closure) {
         return new ClosureUserProvider ($this->user_provider);
      }

      if (is_string ($this->user_provider)) {
         return \Wisp\Container::instance ()->get ($this->user_provider);
      }

      // Default to cache-based provider
      return new CacheUserProvider (
         \Wisp\Container::instance ()->get (\Psr\Cache\CacheItemPoolInterface::class)
      );
   }
}
