<?php

namespace Wisp\Middleware\Authentication;

use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface as CurrentUserStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken as AuthenticatedToken;
use Wisp\Http\Request;
use Wisp\Http\Response;
use Wisp\Security\Contracts\UserProviderInterface;
use Wisp\Security\TokenManager;

class TokenAuthentication
{
   public function __construct (
      private Request $request,
      private Response $response,
      private CurrentUserStorageInterface $tokenStorage,
      private TokenManager $tokenManager,
      private UserProviderInterface $userProvider,
      
      private array $ttl = [
         'access' => 3600, 
         'refresh' => 604800
      ],

      private string $header = 'Authorization',
      private string $scheme = 'Bearer'
   )
   {
   }

   public function before ()
   {
      if (!$this->request->headers->has ($this->header)) {
         return;
      }

      $headerValue = $this->request->headers->get ($this->header);

      if (!str_starts_with ($headerValue, $this->scheme)) {
         return $this->response
            ->status (401)
            ->error ("Token-based authentication requires a {$this->scheme} token in the {$this->header} header");
      }

      $accessToken = substr ($headerValue, strlen ($this->scheme) + 1);

      // Validate token and get session data
      $sessionData = $this->tokenManager->validateAccessToken ($accessToken);

      if (!$sessionData) {
         return $this->response
            ->status (401)
            ->error ('Invalid or expired access token');
      }

      // Load user via provider
      $user = $this->userProvider->loadUser ($sessionData ['user_id']);

      if (!$user) {
         return $this->response
            ->status (401)
            ->error ('User not found');
      }

      // Create authentication token and store it in current user storage
      $token = new AuthenticatedToken ($user, 'main', $user->getRoles ());
      $this->tokenStorage->setToken ($token);
   }
}
