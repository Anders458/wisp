<?php

namespace Example\Controller\Gateway;

use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;
use Wisp\Http\Request;
use Wisp\Http\Response;
use Wisp\Middleware\Authentication\TokenAuthentication;
use Wisp\Middleware\Session;
use Wisp\Contracts\TokenProviderInterface;
use Wisp\Contracts\UserProviderInterface;

class TokenController
{
   public function __construct (
      private SessionInterface $session,
      private TokenProviderInterface $tokenProvider,
      private PasswordHasherInterface $passwordHasher,
      private UserProviderInterface $userProvider,
      private TokenAuthentication $tokenAuthenticationMiddleware
   ) {}

   public function login (Request $request, Response $response) : Response
   {
      $email    = $request->input ('email');
      $password = $request->input ('password');

      $user = $this->userProvider->loadUser ($email);

      if (!$user || !$this->passwordHasher->verify ($user->getPassword (), $password)) {
         return $response
            ->status (401)
            ->error (__ ('gateway.invalid_credentials'));
      }

      if ($this->passwordHasher->needsRehash ($user->getPassword ())) {
         $newHash = $this->passwordHasher->hash ($password);

         // $userRepository->updatePassword (
         //    $user->getId (),
         //    $newHash
         // );
      }

      $tokens = $this->tokenProvider->become (
         userId: $user->getId (),
         roles: $user->getRoles (),
         permissions: $user->getPermissions ()
      );

      return $response->json ($tokens);
   }

   public function refresh (Request $request, Response $response) : Response
   {
      $refreshToken = $request->input ('refresh_token');

      $tokens = $this->tokenProvider->refresh ($refreshToken);

      if (!$tokens) {
         return $response
            ->status (401)
            ->error (__ ('gateway.invalid_refresh_token'));
      }

      return $response->json ($tokens);
   }

   public function logout (Request $request, Response $response) : Response
   {
      $accessToken = $this->tokenAuthenticationMiddleware->getAuthorizationToken ();

      if (!$accessToken) {
         return $response
            ->status (401)
            ->error (__ ('gateway.authorization_required'));
      }

      $revoked = $this->tokenProvider->revoke ($accessToken);

      if (!$revoked) {
         return $response
            ->status (401)
            ->error (__ ('gateway.invalid_expired_token'));
      }

      return $response
         ->status (200)
         ->json (null);
   }
}
