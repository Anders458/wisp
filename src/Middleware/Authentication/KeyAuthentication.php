<?php

namespace Wisp\Middleware\Authentication;

use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface as CurrentUserStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken as AuthenticatedToken;
use Wisp\Http\Request;
use Wisp\Http\Response;
use Wisp\Security\Contracts\KeyValidatorInterface;

/**
 * KeyAuthentication
 *
 * Authenticates users via API key (header or query parameter).
 * Validates the key and populates TokenStorage if valid.
 *
 * This middleware is OPTIONAL - it only validates if a key is present.
 * Use route guards (->is()) to enforce authentication requirements.
 */
class KeyAuthentication
{
   public function __construct (
      private Request $request,
      private Response $response,
      private CurrentUserStorageInterface $tokenStorage,
      private KeyValidatorInterface $validator,
      private string $header = 'X-API-Key',
      private ?string $query = null
   )
   {
   }

   public function before ()
   {
      // Try to get API key from header first
      $apiKey = $this->request->headers->get ($this->header);

      // Fallback to query parameter if configured
      if (!$apiKey && $this->query) {
         $apiKey = $this->request->query->get ($this->query);
      }

      // No key present - skip validation
      if (!$apiKey) {
         return;
      }

      // Validate API key
      $user = $this->validator->validate ($apiKey);

      if (!$user) {
         return $this->response
            ->status (401)
            ->error ('Invalid API key');
      }

      // Create authentication token and store it
      $token = new AuthenticatedToken ($user, 'main', $user->getRoles ());
      $this->tokenStorage->setToken ($token);
   }
}
