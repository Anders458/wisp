<?php

namespace Wisp\Middleware\Authentication;

use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface as CurrentUserStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken as UserAuthContext;
use Wisp\Http\Request;
use Wisp\Http\Response;
use Wisp\Contracts\TokenProviderInterface;
use Wisp\Contracts\UserProviderInterface;

class TokenAuthentication
{
   public function __construct (
      private Request $request,
      private Response $response,
      private CurrentUserStorageInterface $currentUserStorage,
      private TokenProviderInterface $tokenProvider,
      private UserProviderInterface $userProvider,
      private string $header = 'Authorization',
      private string $scheme = 'Bearer'
   )
   {
   }

   public function before () : ?Response
   {
      if (! ($accessToken = $this->getAuthorizationToken ())) {
         return null;
      }

      $sessionData = $this->tokenProvider->validate ($accessToken);

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

      $authContext = new UserAuthContext ($user, 'main', $user->getRoles ());
      $this->currentUserStorage->setToken ($authContext);

      return null;
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
