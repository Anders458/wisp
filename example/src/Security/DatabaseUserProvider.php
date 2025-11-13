<?php

namespace Example\Security;

use Symfony\Component\Security\Core\User\UserInterface;
use Wisp\Security\Contracts\UserProviderInterface;
use Wisp\Security\User;

class DatabaseUserProvider implements UserProviderInterface
{
   // Simulated database - in real app, this would be a database query
   private array $users = [
      [
         'id' => 1,
         'email' => 'user@example.com',
         'role' => 'user',
         'permissions' => [ 'read:own', 'write:own' ],
         'password' => '$2y$13$gZWracOSRGuEQsz7ltGqCuevzdeBB6ednqL5o7a5U3iyiz5VcQ/J.' // "secret"
      ],

      [
         'id' => 2,
         'email' => 'admin@example.com',
         'role' => 'admin',
         'permissions' => [ '*' ],
         'password' => '$2y$13$gZWracOSRGuEQsz7ltGqCuevzdeBB6ednqL5o7a5U3iyiz5VcQ/J.' // "secret"
      ]
   ];

   public function loadUser (string | int $identifier) : ?UserInterface
   {
      foreach ($this->users as $userData) {
         if (
            $userData ['id'] === $identifier ||
            $userData ['email'] === $identifier
         ) {
            return new User (
               id: $userData ['id'],
               role: $userData ['role'],
               permissions: $userData ['permissions'],
               password: $userData ['password']
            );
         }
      }

      return null;
   }
}
