<?php

namespace Wisp\Middleware\Authentication;

use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface as CurrentUserStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken as AuthenticatedToken;
use Wisp\Http\Request;
use Wisp\Http\Response;
use Wisp\Security\Contracts\ApiKeyValidatorInterface;

class ApiKeyAuthentication
{
   private ?ApiKeyValidatorInterface $resolvedValidator = null;

   public function __construct (
      private Request $request,
      private Response $response,
      private CurrentUserStorageInterface $tokenStorage,
      ?ApiKeyValidatorInterface $validatorInstance = null,
      private mixed $validator = null,
      private string $header = 'X-API-Key',
      private ?string $query = null
   )
   {
      // Resolve validator
      $this->resolvedValidator = $this->resolveValidator ($validatorInstance);
   }

   public function before ()
   {
      // Try to get API key from header first
      $apiKey = $this->request->headers->get ($this->header);

      // Fallback to query parameter if configured
      if (!$apiKey && $this->query) {
         $apiKey = $this->request->query->get ($this->query);
      }

      if (!$apiKey) {
         return;
      }

      if (!$this->resolvedValidator) {
         return $this->response
            ->status (500)
            ->error ('API key validator not configured');
      }

      // Validate API key
      $user = $this->resolvedValidator->validate ($apiKey);

      if (!$user) {
         return $this->response
            ->status (401)
            ->error ('Invalid API key');
      }

      // Create authentication token and store it in current user storage
      $token = new AuthenticatedToken ($user, 'main', $user->getRoles ());
      $this->tokenStorage->setToken ($token);
   }

   private function resolveValidator (?ApiKeyValidatorInterface $instance) : ?ApiKeyValidatorInterface
   {
      // Container-registered validator takes precedence
      if ($instance !== null) {
         return $instance;
      }

      // Fallback to validator setting
      if ($this->validator instanceof ApiKeyValidatorInterface) {
         return $this->validator;
      }

      if ($this->validator instanceof \Closure) {
         return new class ($this->validator) implements ApiKeyValidatorInterface {
            public function __construct (private \Closure $closure) {}

            public function validate (string $plaintext) : ?\Symfony\Component\Security\Core\User\UserInterface
            {
               $container = \Wisp\Container::instance ();
               $bound = $this->closure->bindTo (new class ($container) {
                  public function __construct (public \Wisp\Container $container) {}
               });

               $result = $bound ($plaintext);

               return $result instanceof \Symfony\Component\Security\Core\User\UserInterface ? $result : null;
            }
         };
      }

      if (is_string ($this->validator)) {
         return \Wisp\Container::instance ()->get ($this->validator);
      }

      return null;
   }
}
