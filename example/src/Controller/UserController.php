<?php

namespace Example\Controller;

use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface as CurrentUserStorageInterface;
use Wisp\Http\Response;

class UserController
{
   public function __construct (
      private Response $response,
      private CurrentUserStorageInterface $tokenStorage
   ) {}

   public function me () : Response
   {
      $token = $this->tokenStorage->getToken ();

      if (!$token || !$token->getUser ()) {
         return $this->response
            ->status (401)
            ->error ('Authentication required');
      }

      $user = $token->getUser ();

      return $this->response->json ([
         'id' => $user->getId (),
         'role' => $user->getRole (),
         'permissions' => $user->getPermissions (),
         'roles' => $user->getRoles ()
      ]);
   }
}