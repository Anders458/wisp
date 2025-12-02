<?php

namespace App\Controller;

use App\Shape\CreateUserRequest;
use Wisp\Attribute\Can;
use Wisp\Attribute\Is;
use Wisp\Attribute\Throttle;
use Wisp\Attribute\Validated;
use Wisp\Http\Request;
use Wisp\Http\Response;
use Symfony\Component\Routing\Attribute\Route;

class UserController
{
   #[Route ('/api/users', methods: [ 'GET' ])]
   #[Is ('ROLE_USER')]
   public function index (Request $request): Response
   {
      $page = $request->input ('page', 1);
      $limit = $request->input ('limit', 10);

      return (new Response)
         ->json ([
            'users' => [
               [ 'id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com' ],
               [ 'id' => 2, 'name' => 'Bob', 'email' => 'bob@example.com' ]
            ],
            'meta' => [
               'page' => (int) $page,
               'limit' => (int) $limit,
               'total' => 2
            ]
         ]);
   }

   #[Route ('/api/users', methods: [ 'POST' ])]
   #[Throttle (limit: 5, interval: 60)]
   public function store (#[Validated] CreateUserRequest $dto): Response
   {
      return (new Response)
         ->status (201)
         ->json ([
            'id' => 3,
            'email' => $dto->email,
            'name' => $dto->name
         ]);
   }

   #[Route ('/api/users/{id}', methods: [ 'GET' ])]
   #[Is ('ROLE_USER')]
   public function show (Request $request, int $id): Response
   {
      return (new Response)
         ->json ([
            'id' => $id,
            'name' => 'Alice',
            'email' => 'alice@example.com'
         ])
         ->cache (3600);
   }

   #[Route ('/api/admin/users', methods: [ 'GET' ])]
   #[Is ('ROLE_ADMIN')]
   public function adminIndex (): Response
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

   #[Route ('/api/health', methods: [ 'GET' ])]
   public function health (): Response
   {
      return (new Response)->json ([ 'status' => 'ok' ]);
   }
}
