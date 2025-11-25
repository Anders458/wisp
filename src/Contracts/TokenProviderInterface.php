<?php

namespace Wisp\Contracts;

interface TokenProviderInterface
{
   public function become (int | string $userId, array $roles, array $permissions) : array;

   public function refresh (string $refreshToken) : ?array;

   public function revoke (string $token) : bool;

   public function validate (string $accessToken) : ?array;

   public function list () : array;
}
