<?php

namespace Example\Controller;

use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface as CurrentUserStorageInterface;
use Wisp\Http\Request;
use Wisp\Http\Response;

class UserController
{
   public function __construct (
      private CurrentUserStorageInterface $currentUserStorage
   ) {}

   public function me (Request $request, Response $response) : Response
   {
      // Guard already ensured user is authenticated
      $user = $this->currentUserStorage->getToken ()->getUser ();

      return $response->json ([
         'user' => [
            'id' => $user->getId (),
            'roles' => $user->getRoles (),
            'permissions' => $user->getPermissions ()
         ]
      ]);
   }
}