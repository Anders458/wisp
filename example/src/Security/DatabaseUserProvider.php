<?php

namespace Example\Security;

use Symfony\Component\Security\Core\User\UserInterface;
use Wisp\Security\Contracts\UserProviderInterface;
use Wisp\Security\User;

class DatabaseUserProvider implements UserProviderInterface
{
   public function loadUser (string | int $identifier) : ?UserInterface
   {
      $users = [
         1 => [ 'id' => 1, 'role' => 'user',  'permissions' => ['read:own', 'write:own'] ],
         2 => [ 'id' => 2, 'role' => 'admin', 'permissions' => ['*'] ]
      ];

      if (!isset ($users [$identifier])) {
         return null;
      }

      $data = $users [$identifier];

      return new User (
         id: $data ['id'],
         role: $data ['role'],
         permissions: $data ['permissions']
      );
   }
}
