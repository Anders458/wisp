<?php

namespace App\Entity;

use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class User implements UserInterface, PasswordAuthenticatedUserInterface
{
   public function __construct (
      private string $id,
      private string $email,
      private string $password,
      private array $roles = []
   )
   {
   }

   public function getId (): string
   {
      return $this->id;
   }

   public function getEmail (): string
   {
      return $this->email;
   }

   public function getUserIdentifier (): string
   {
      return $this->email;
   }

   public function getPassword (): string
   {
      return $this->password;
   }

   public function getRoles (): array
   {
      $roles = $this->roles;
      $roles [] = 'ROLE_USER';
      return array_unique ($roles);
   }

   public function eraseCredentials (): void
   {
   }
}
