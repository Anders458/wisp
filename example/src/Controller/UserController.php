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
      // Guard already ensured user is authenticated
      $user = $this->tokenStorage->getToken ()->getUser ();

      return $this->response->json ([
         'id' => $user->getId (),
         'role' => $user->getRole (),
         'permissions' => $user->getPermissions (),
         'roles' => $user->getRoles ()
      ]);
   }
}