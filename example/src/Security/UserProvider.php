<?php

namespace App\Security;

use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class UserProvider implements UserProviderInterface
{
   public function loadUserByIdentifier (string $identifier): UserInterface
   {
      throw new \RuntimeException ('Not implemented - use token authenticator');
   }

   public function refreshUser (UserInterface $user): UserInterface
   {
      throw new UnsupportedUserException ('Stateless API - refresh not supported');
   }

   public function supportsClass (string $class): bool
   {
      return true;
   }
}
