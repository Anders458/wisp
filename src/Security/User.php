<?php

namespace Wisp\Security;

use Symfony\Component\Security\Core\User\UserInterface as SymfonyUserInterface;

class User implements SymfonyUserInterface
{
   public function __construct (
      private int | string $id,
      private array $roles,
      private array $permissions = [],
      private ?string $password = null
   )
   {
   }

   public function eraseCredentials () : void
   {
   }

   public function getId () : int | string
   {
      return $this->id;
   }

   public function getPassword () : ?string
   {
      return $this->password;
   }

   public function getPermissions () : array
   {
      return $this->permissions;
   }

   public function getRoles () : array
   {
      return array_map (fn ($role) => strtoupper ($role), $this->roles);
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
