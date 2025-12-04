<?php

namespace App\Controller;

use App\Shape\LoginRequest;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Wisp\Attribute\Throttle;
use Wisp\Http\Request;
use Wisp\Http\Response;
use Wisp\Service\Flash;

#[Route ('/v1/auth')]
class AuthController
{
   // Demo users for example purposes
   private const USERS = [
      'user@example.com' => [
         'id' => 1,
         'password' => 'password123',
         'name' => 'Alice',
         'roles' => [ 'ROLE_USER' ]
      ],
      'admin@example.com' => [
         'id' => 2,
         'password' => 'admin123',
         'name' => 'Admin',
         'roles' => [ 'ROLE_USER', 'ROLE_ADMIN' ]
      ]
   ];

   /**
    * Token-based authentication.
    * Returns a bearer token for API usage.
    *
    * POST /v1/auth/token
    * {"email": "user@example.com", "password": "password123"}
    */
   #[Route ('/token', methods: [ 'POST' ])]
   #[Throttle (limit: 100, interval: 60)]
   public function token (#[MapRequestPayload] LoginRequest $dto, Flash $flash): Response
   {
      $user = $this->authenticate ($dto->email, $dto->password);

      if ($user === null) {
         $flash->error ('Invalid email or password', 'auth:invalid_credentials');

         return (new Response)
            ->status (401)
            ->json (null);
      }

      // In a real app, generate a JWT or database token
      $token = $this->generateToken ($user);

      return (new Response)
         ->json ([
            'token' => $token,
            'type' => 'bearer',
            'expires_in' => 3600,
            'user' => [
               'id' => $user ['id'],
               'email' => $dto->email,
               'name' => $user ['name'],
               'roles' => $user ['roles']
            ]
         ]);
   }

   /**
    * Session-based authentication.
    * Sets an HTTP-only cookie for browser usage.
    *
    * POST /v1/auth/session
    * {"email": "user@example.com", "password": "password123"}
    */
   #[Route ('/session', methods: [ 'POST' ])]
   #[Throttle (limit: 100, interval: 60)]
   public function session (#[MapRequestPayload] LoginRequest $dto, Request $request, Flash $flash): Response
   {
      $user = $this->authenticate ($dto->email, $dto->password);

      if ($user === null) {
         $flash->error ('Invalid email or password', 'auth:invalid_credentials');

         return (new Response)
            ->status (401)
            ->json (null);
      }

      // Store user in session
      $session = $request->getSession ();
      $session->set ('user_id', $user ['id']);
      $session->set ('user_email', $dto->email);
      $session->set ('user_roles', $user ['roles']);

      $flash->success ('Logged in successfully');

      $response = (new Response)
         ->json ([
            'user' => [
               'id' => $user ['id'],
               'email' => $dto->email,
               'name' => $user ['name'],
               'roles' => $user ['roles']
            ]
         ]);

      // Session cookie is handled automatically by Symfony
      // But we can add a "remember me" cookie if needed
      if ($dto->remember ?? false) {
         $response->headers->setCookie (
            Cookie::create ('remember_me')
               ->withValue ($this->generateToken ($user))
               ->withExpires (time () + 86400 * 30)
               ->withHttpOnly (true)
               ->withSecure (true)
               ->withSameSite ('lax')
         );
      }

      return $response;
   }

   /**
    * Logout - clear session and cookies.
    *
    * POST /v1/auth/logout
    */
   #[Route ('/logout', methods: [ 'POST' ])]
   public function logout (Request $request, Flash $flash): Response
   {
      if ($request->hasSession ()) {
         $request->getSession ()->invalidate ();
      }

      $flash->success ('Logged out successfully');

      $response = (new Response)->json (null);

      // Clear remember_me cookie
      $response->headers->clearCookie ('remember_me');

      return $response;
   }

   /**
    * @return array{id: int, password: string, name: string, roles: string[]}|null
    */
   private function authenticate (string $email, string $password): ?array
   {
      $user = self::USERS [$email] ?? null;

      if ($user === null || $user ['password'] !== $password) {
         return null;
      }

      return $user;
   }

   /**
    * @param array{id: int, name: string, roles: string[]} $user
    */
   private function generateToken (array $user): string
   {
      // Demo: return mock tokens that ApiTokenAuthenticator accepts
      // In a real app, use JWT or a secure token generator
      $mockTokens = [
         1 => 'user-token-123',
         2 => 'admin-token-456'
      ];

      return $mockTokens [$user ['id']] ?? base64_encode (json_encode ([
         'user_id' => $user ['id'],
         'roles' => $user ['roles'],
         'exp' => time () + 3600
      ]));
   }

   /**
    * @return array{id: int, roles: string[]}|null
    */
   private function validateToken (string $token): ?array
   {
      try {
         $decoded = json_decode (base64_decode ($token), true);

         if (!is_array ($decoded)) {
            return null;
         }

         if (($decoded ['exp'] ?? 0) < time ()) {
            return null;
         }

         return [
            'id' => $decoded ['user_id'] ?? null,
            'roles' => $decoded ['roles'] ?? []
         ];
      } catch (\Throwable) {
         return null;
      }
   }
}
