<?php

namespace Example\Controller\Gateway;

use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;
use Wisp\Http\Request;
use Wisp\Http\Response;
use Wisp\Middleware\Session;
use Wisp\Security\Contracts\UserProviderInterface;

class CookieController
{
   public function __construct (
      private Request $request,
      private Response $response,
      private SessionInterface $session,
      private Session $sessionMiddleware,
      private PasswordHasherInterface $passwordHasher,
      private UserProviderInterface $userProvider
   ) {}

   public function login () : Response
   {
      $email    = $this->request->input ('email');
      $password = $this->request->input ('password');

      // Validate inputs
      if (!$email || !$password) {
         return $this->response
            ->status (400)
            ->error ('Email and password are required');
      }

      // Load user by email
      $user = $this->userProvider->loadUser ($email);

      if (!$user || !$this->passwordHasher->verify ($user->getPassword (), $password)) {
         return $this->response
            ->status (401)
            ->error ('Invalid credentials');
      }

      // Password rehash if needed
      if ($this->passwordHasher->needsRehash ($user->getPassword ())) {
         $newHash = $this->passwordHasher->hash ($password);

         // In production, update the password in database
         // $userRepository->updatePassword ($user->getId (), $newHash);
      }

      // Regenerate session ID to prevent session fixation
      $this->sessionMiddleware->regenerate (true);

      // Store user ID in session
      $this->session->set ('user_id', $user->getId ());

      return $this->response
         ->status (200)
         ->json ([
            'user' => [
               'id' => $user->getId (),
               'role' => $user->getRole (),
               'permissions' => $user->getPermissions ()
            ]
         ]);
   }

   public function logout () : Response
   {
      // Clear user_id from session
      $this->session->remove ('user_id');

      // Invalidate the session
      $this->session->invalidate ();

      return $this->response
         ->status (200)
         ->json ();
   }
}
