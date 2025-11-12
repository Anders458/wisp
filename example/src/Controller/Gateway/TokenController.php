<?php

namespace Example\Controller\Gateway;

use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;
use Wisp\Http\Request;
use Wisp\Http\Response;
use Wisp\Middleware\Session;
use Wisp\Security\TokenManager;

class TokenController
{
   public function __construct (
      private TokenManager $tokenManager,
      private SessionInterface $session,
      private Session $sessionMiddleware,
      private PasswordHasherInterface $passwordHasher,
      private Response $response
   ) {}

   public function login (Request $request) : Response
   {
      $email    = $request->input ('email');
      $password = $request->input ('password');

      // TODO: Replace with real database lookup
      // Example: $user = User::where ('email', $email)->first ();
      $storedHash = '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/LewY5oPLQOZw4BQGG'; // "secret"

      // SECURITY: Verify password using constant-time comparison
      if ($email !== 'user@example.com' || !$this->passwordHasher->verify ($password, $storedHash)) {
         return $this->response
            ->status (401)
            ->json (['error' => 'Invalid credentials']);
      }

      // SECURITY: Check if password needs rehashing (algorithm/cost changed)
      if ($this->passwordHasher->needsRehash ($storedHash)) {
         $newHash = $this->passwordHasher->hash ($password);
         // TODO: Update database with new hash
         // Example: $user->update (['password' => $newHash]);
      }

      // Generate tokens
      $tokens = $this->tokenManager->become (
         userId: 1,
         role: 'user',
         permissions: ['read:own', 'write:own']
      );

      return $this->response->json ($tokens);
   }

   /**
    * Refresh access token
    * POST /auth/token/refresh
    * Body: {"refresh_token": "..."}
    */
   public function refresh (Request $request) : Response
   {
      $refreshToken = $request->input ('refresh_token');

      $tokens = $this->tokenManager->refresh ($refreshToken);

      if (!$tokens) {
         return $this->response
            ->status (401)
            ->json (['error' => 'Invalid refresh token']);
      }

      return $this->response->json ($tokens);
   }

   /**
    * Logout (revoke tokens)
    * POST /auth/token/logout
    * Body: {"refresh_token": "..."}
    */
   public function logout (Request $request) : Response
   {
      $refreshToken = $request->input ('refresh_token');

      $this->tokenManager->revoke ($refreshToken);

      return $this->response->json (['message' => 'Logged out']);
   }

   /**
    * Web-based login (session)
    * POST /auth/web/login
    * Body: {"email": "admin@example.com", "password": "secret"}
    */
   public function webLogin (Request $request) : Response
   {
      $email = $request->input ('email');
      $password = $request->input ('password');

      // TODO: Replace with real database lookup
      $storedHash = '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/LewY5oPLQOZw4BQGG'; // "secret"

      // SECURITY: Verify password using constant-time comparison
      if ($email !== 'admin@example.com' || !$this->passwordHasher->verify ($password, $storedHash)) {
         return $this->response
            ->status (401)
            ->json (['error' => 'Invalid credentials']);
      }

      // SECURITY: Regenerate session ID to prevent session fixation
      $this->sessionMiddleware->regenerate (true);

      // Set user in session
      $this->session->set ('user_id', 1);
      $this->session->set ('role', 'admin');
      $this->session->set ('permissions', ['*']);

      return $this->response->json (['message' => 'Logged in']);
   }

   /**
    * Web-based logout
    * POST /auth/web/logout
    */
   public function webLogout () : Response
   {
      $this->session->invalidate ();

      return $this->response->json (['message' => 'Logged out']);
   }
}
