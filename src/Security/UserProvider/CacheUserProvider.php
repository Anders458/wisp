<?php

namespace Wisp\Security\UserProvider;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Wisp\Security\Contracts\UserProviderInterface;
use Wisp\Security\User;

class CacheUserProvider implements UserProviderInterface
{
   public function __construct (
      private CacheItemPoolInterface $cache
   )
   {
   }

   public function loadUser (string | int $identifier) : ?UserInterface
   {
      $item = $this->cache->getItem ("wisp:user:{$identifier}");

      if (!$item->isHit ()) {
         return null;
      }

      $data = $item->get ();

      if (!is_array ($data) || !isset ($data ['id'], $data ['role'])) {
         return null;
      }

      return new User (
         id: $data ['id'],
         role: $data ['role'],
         permissions: $data ['permissions'] ?? []
      );
   }

   public function loadUserByIdentifier (string $identifier) : ?UserInterface
   {
      // CacheUserProvider is designed for loading users by ID (session/token restoration)
      // For authentication by email/username, use a database-backed provider
      // This implementation attempts to load by treating identifier as an ID
      return $this->loadUser ($identifier);
   }
}
