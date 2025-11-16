<?php

namespace Example\Controller;

use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface as CurrentUserStorageInterface;
use Wisp\Http\Response;

class ProfileController
{
   public function __construct (
      private CurrentUserStorageInterface $tokenStorage,
      private Response $response
   ) {}

   /**
    * Get current user profile
    * GET /api/v1/profile
    */
   public function show () : Response
   {
      $user = $this->tokenStorage->getToken ()?->getUser ();

      if (!$user) {
         return $this->response
            ->status (401)
            ->json (['error' => 'Not authenticated']);
      }

      return $this->response->json ([
         'id' => $user->getUserIdentifier (),
         'roles' => $user->getRoles (),
         'permissions' => $user->getPermissions ()
      ]);
   }

   /**
    * List all users (admin only)
    * GET /api/v1/admin/users
    */
   public function listUsers () : Response
   {
      // This endpoint requires 'admin' role and 'read:users' permission
      // Enforced by ->is('admin')->can('read:users') on the route

      return $this->response->json ([
         'users' => [
            ['id' => 1, 'email' => 'user@example.com', 'role' => 'user'],
            ['id' => 2, 'email' => 'admin@example.com', 'role' => 'admin']
         ]
      ]);
   }
}
