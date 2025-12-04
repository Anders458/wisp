<?php

namespace App\Controller;

use App\Entity\User;
use App\Shape\CreateUserRequest;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Wisp\Attribute\Is;
use Wisp\Attribute\Throttle;
use Wisp\Http\Request;
use Wisp\Http\Response;
use Wisp\Service\Flash;

#[Route ('/v1/users')]
class UserController
{
   public function __construct (
      private TokenStorageInterface $tokenStorage
   ) {}

   /**
    * Get the current authenticated user.
    *
    * GET /v1/users/@me
    */
   #[Route ('/@me', methods: [ 'GET' ])]
   public function me (Request $request, Flash $flash): Response
   {
      // Check Symfony security context first (covers token auth from api firewall)
      $token = $this->tokenStorage->getToken ();

      if ($token !== null) {
         $user = $token->getUser ();

         if ($user instanceof User) {
            return (new Response)
               ->json ([
                  'id' => (int) $user->getId (),
                  'email' => $user->getEmail (),
                  'roles' => $user->getRoles ()
               ]);
         }
      }

      // Check session (for session-based auth)
      if ($request->hasSession ()) {
         $session = $request->getSession ();
         $userId = $session->get ('user_id');

         if ($userId !== null) {
            return (new Response)
               ->json ([
                  'id' => $userId,
                  'email' => $session->get ('user_email'),
                  'roles' => $session->get ('user_roles')
               ]);
         }
      }

      $flash->error ('Not authenticated', 'auth:unauthenticated');

      return (new Response)
         ->status (401)
         ->json (null);
   }

   #[Route ('', methods: [ 'GET' ])]
   #[Is ('user')]
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

   #[Route ('', methods: [ 'POST' ])]
   #[Throttle (limit: 100, interval: 60)]
   public function store (#[MapRequestPayload] CreateUserRequest $dto): Response
   {
      return (new Response)
         ->status (201)
         ->json ([
            'id' => 3,
            'email' => $dto->email,
            'name' => $dto->name
         ]);
   }

   #[Route ('/{id}', methods: [ 'GET' ], requirements: [ 'id' => '\d+' ])]
   #[Is ('user')]
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

}
