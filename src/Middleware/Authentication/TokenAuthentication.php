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
      private string $header = 'Authorization',
      private string $scheme = 'Bearer'
   )
   {
   }

   public function before ()
   {
      // If no Bearer token present, skip (don't fail - let guards handle auth requirement)
      if (! ($accessToken = $this->getAuthorizationToken ())) {
         return;
      }

      // Only validate if Bearer token IS present
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
