<?php

namespace Wisp\Security;

use Symfony\Component\Security\Core\User\UserInterface as SymfonyUserInterface;

class User implements SymfonyUserInterface
{
   public function __construct (
      private int | string $id,
      private string $role,
      private array $permissions = []
   )
   {
   }

   public function eraseCredentials () : void
   {
      // No credentials stored in user object
   }

   public function getId () : int | string
   {
      return $this->id;
   }

   public function getPermissions () : array
   {
      return $this->permissions;
   }

   public function getRole () : string
   {
      return $this->role;
   }

   public function getRoles () : array
   {
      return [strtoupper ($this->role)];
   }

   public function getUserIdentifier () : string
   {
      return (string) $this->id;
   }

   public function hasPermission (string $permission) : bool
   {
      return in_array ($permission, $this->permissions);
   }
}
