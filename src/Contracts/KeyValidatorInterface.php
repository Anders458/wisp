<?php

namespace Wisp\Contracts;

use Symfony\Component\Security\Core\User\UserInterface;

interface KeyValidatorInterface
{
   public function validate (string $key) : ?UserInterface;

   public function store (string $key, int | string $userId, array $roles = [], array $permissions = [], ?int $ttl = null) : void;

   public function revoke (string $key) : bool;

   public function list () : array;
}
