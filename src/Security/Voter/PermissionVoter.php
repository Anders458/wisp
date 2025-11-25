<?php

namespace Wisp\Security\Voter;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface as AuthContext;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Wisp\Security\User;

class PermissionVoter extends Voter
{
   protected function supports (string $attribute, mixed $subject) : bool
   {
      // Permissions have colon separator (e.g., "create:posts", "read:data")
      return str_contains ($attribute, ':');
   }

   protected function voteOnAttribute (string $attribute, mixed $subject, AuthContext $authContext, ?Vote $vote = null) : bool
   {
      $user = $authContext->getUser ();

      if (!$user instanceof User) {
         return false;
      }

      return $user->hasPermission ($attribute);
   }
}
