<?php

namespace App\Controller;

use Symfony\Component\Routing\Attribute\Route;
use Wisp\Attribute\Is;
use Wisp\Http\Response;

#[Route ('/v1/admin')]
#[Is ('admin')]
class AdminController
{
   #[Route ('/users', methods: [ 'GET' ])]
   public function users (): Response
   {
      return (new Response)
         ->json ([
            'users' => [
               [ 'id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com', 'role' => 'user' ],
               [ 'id' => 2, 'name' => 'Admin', 'email' => 'admin@example.com', 'role' => 'admin' ]
            ],
            'admin_only' => true
         ]);
   }
}
