<?php

namespace Wisp\Middleware\Authentication;

use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface as CurrentUserStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken as AuthenticatedToken;
use Wisp\Http\Request;
use Wisp\Http\Response;
use Wisp\Security\AccessTokenProvider;
use Wisp\Security\Contracts\UserProviderInterface;

class TokenAuthentication
{
   public function __construct (
      private Request $request,
      private Response $response,
      private CurrentUserStorageInterface $tokenStorage,
      private AccessTokenProvider $accessTokenProvider,
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
      // Check if user is already authenticated (e.g., via CookieAuthentication)
      $existingToken = $this->tokenStorage->getToken ();
      if ($existingToken && $existingToken->getUser ()) {
         // User already authenticated via another method, skip token validation
         return;
      }

      if (! ($accessToken = $this->getAuthorizationToken ())) {
         return $this->response
            ->status (401)
            ->error ("Token-based authentication requires a {$this->scheme} token in the {$this->header} header");
      }

      $sessionData = $this->accessTokenProvider->validate ($accessToken);

      if (!$sessionData) {
         return $this->response
            ->status (401)
            ->error ('Invalid or expired access token');
      }

      $user = $this->userProvider->loadUser ($sessionData ['user_id']);

      if (!$user) {
         return $this->response
            ->status (401)
            ->error ('User not found');
      }

      $token = new AuthenticatedToken ($user, 'main', $user->getRoles ());
      $this->tokenStorage->setToken ($token);
   }

   public function getAuthorizationToken () : ?string
   {
      if (!$this->request->headers->has ($this->header)) {
         return null;
      }

      $headerValue = $this->request->headers->get ($this->header);

      if (!str_starts_with ($headerValue, $this->scheme)) {
         return null;
      }

      $accessToken = substr ($headerValue, strlen ($this->scheme) + 1);
      return $accessToken;
   }
}
