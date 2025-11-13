<?php

namespace Example\Controller\Gateway;

use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;
use Wisp\Http\Request;
use Wisp\Http\Response;
use Wisp\Middleware\Authentication\TokenAuthentication;
use Wisp\Middleware\Session;
use Wisp\Security\AccessTokenProvider;
use Wisp\Security\Contracts\UserProviderInterface;

class TokenController
{
   public function __construct (
      private Response $response,
      private SessionInterface $session,
      private AccessTokenProvider $accessTokenProvider,
      private PasswordHasherInterface $passwordHasher,
      private UserProviderInterface $userProvider,
      private TokenAuthentication $tokenAuthenticationMiddleware
   ) {}

   public function login (Request $request) : Response
   {
      $email    = $request->input ('email');
      $password = $request->input ('password');

      $user = $this->userProvider->loadUser ($email);

      if (!$user || !$this->passwordHasher->verify ($user->getPassword (), $password)) {
         return $this->response
            ->status (401)
            ->error ('Invalid credentials');
      }

      if ($this->passwordHasher->needsRehash ($user->getPassword ())) {
         $newHash = $this->passwordHasher->hash ($password);
         
         // $userRepository->updatePassword (
         //    $user->getId (), 
         //    $newHash
         // );
      }

      $tokens = $this->accessTokenProvider->become (
         userId: $user->getId (),
         role: $user->getRole (),
         permissions: $user->getPermissions ()
      );

      return $this->response->json ($tokens);
   }

   public function refresh (Request $request) : Response
   {
      $refreshToken = $request->input ('refresh_token');

      $tokens = $this->accessTokenProvider->refresh ($refreshToken);

      if (!$tokens) {
         return $this->response
            ->status (401)
            ->error ('Invalid refresh token');
      }

      return $this->response->json ($tokens);
   }

   public function logout () : Response
   {
      $accessToken = $this->tokenAuthenticationMiddleware->getAuthorizationToken ();

      if (!$accessToken) {
         return $this->response
            ->status (401)
            ->error ('Authorization header required');
      }

      $revoked = $this->accessTokenProvider->revoke ($accessToken);

      if (!$revoked) {
         return $this->response
            ->status (401)
            ->error ('Invalid or expired token');
      }

      return $this->response
         ->status (200)
         ->json (null);
   }
}
