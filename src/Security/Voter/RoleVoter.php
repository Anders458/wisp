<?php

namespace Wisp\Security\Voter;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface as AuthenticationToken;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Wisp\Security\User;

class RoleVoter extends Voter
{
   protected function supports (string $attribute, mixed $subject) : bool
   {
      // Support any attribute that doesn't contain a colon (permissions use colon separator)
      return !str_contains ($attribute, ':');
   }

   protected function voteOnAttribute (string $attribute, mixed $subject, AuthenticationToken $token) : bool
   {
      $user = $token->getUser ();

      if (!$user instanceof User) {
         return false;
      }

      return in_array ($attribute, $user->getRoles ());
   }
}
