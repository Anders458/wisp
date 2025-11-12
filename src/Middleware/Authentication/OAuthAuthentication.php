<?php

namespace Wisp\Middleware\Authentication;

use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface as CurrentUserStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken as AuthenticatedToken;
use Wisp\Http\Request;
use Wisp\Security\Contracts\OAuthUserMapperInterface;
use Wisp\Security\Contracts\UserProviderInterface;
use Wisp\Security\OAuth\OAuthManager;
use Wisp\Security\UserProvider\ClosureUserProvider;

class OAuthAuthentication
{
   private ?OAuthUserMapperInterface $userMapper = null;
   private ?UserProviderInterface $userProvider = null;

   public function __construct (
      private Request $request,
      private SessionInterface $session,
      private CurrentUserStorageInterface $tokenStorage,
      ?OAuthUserMapperInterface $userMapperInstance = null,
      ?UserProviderInterface $userProviderInstance = null,
      private array $providers = [],
      private mixed $user_mapper = null,
      private mixed $user_provider = null
   )
   {
      // Resolve mapper and provider
      $this->userMapper = $this->resolveUserMapper ($userMapperInstance);
      $this->userProvider = $this->resolveUserProvider ($userProviderInstance);
   }

   public function before ()
   {
      // OAuth authentication is typically handled in controllers
      // This middleware just checks if a user is already authenticated via OAuth in the session
      if (!$this->session->has ('user_id')) {
         return;
      }

      $userId = $this->session->get ('user_id');

      if (!$this->userProvider) {
         return;
      }

      // Load user via provider
      $user = $this->userProvider->loadUser ($userId);

      if (!$user) {
         $this->session->remove ('user_id');
         return;
      }

      // Create authentication token and store it in current user storage
      $token = new AuthenticatedToken ($user, 'main', $user->getRoles ());
      $this->tokenStorage->setToken ($token);
   }

   private function resolveUserMapper (?OAuthUserMapperInterface $instance) : ?OAuthUserMapperInterface
   {
      if ($instance !== null) {
         return $instance;
      }

      if ($this->user_mapper instanceof OAuthUserMapperInterface) {
         return $this->user_mapper;
      }

      if (is_string ($this->user_mapper)) {
         return \Wisp\Container::instance ()->get ($this->user_mapper);
      }

      return null;
   }

   private function resolveUserProvider (?UserProviderInterface $instance) : ?UserProviderInterface
   {
      if ($instance !== null) {
         return $instance;
      }

      if ($this->user_provider instanceof UserProviderInterface) {
         return $this->user_provider;
      }

      if ($this->user_provider instanceof \Closure) {
         return new ClosureUserProvider ($this->user_provider);
      }

      if (is_string ($this->user_provider)) {
         return \Wisp\Container::instance ()->get ($this->user_provider);
      }

      return null;
   }
}
