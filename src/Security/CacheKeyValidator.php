<?php

namespace Wisp\Security;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Wisp\Contracts\KeyValidatorInterface;
use Wisp\Security\User;

class CacheKeyValidator implements KeyValidatorInterface
{
   public function __construct (
      private CacheItemPoolInterface $cache
   )
   {
   }

   public function validate (string $key) : ?UserInterface
   {
      $hashedKey = hash ('sha256', $key);
      $cacheKey = "wisp:api_key:{$hashedKey}";

      $item = $this->cache->getItem ($cacheKey);

      if (!$item->isHit ()) {
         return null;
      }

      $data = $item->get ();

      return new User (
         $data ['user_id'],
         $data ['roles'] ?? [],
         $data ['permissions'] ?? []
      );
   }

   public function store (string $key, int | string $userId, array $roles = [], array $permissions = [], ?int $ttl = null) : void
   {
      $hashedKey = hash ('sha256', $key);
      $cacheKey = "wisp:api_key:{$hashedKey}";

      $data = [
         'user_id' => $userId,
         'roles' => $roles,
         'permissions' => $permissions,
         'created_at' => time (),
         'key_hash' => $hashedKey
      ];

      $item = $this->cache->getItem ($cacheKey);
      $item->set ($data);

      if ($ttl) {
         $item->expiresAfter ($ttl);
      }

      $this->cache->save ($item);

      $this->addToRegistry ($hashedKey, $data);
   }

   public function revoke (string $key) : bool
   {
      $hashedKey = hash ('sha256', $key);
      $cacheKey = "wisp:api_key:{$hashedKey}";

      $deleted = $this->cache->deleteItem ($cacheKey);

      if ($deleted) {
         $this->removeFromRegistry ($hashedKey);
      }

      return $deleted;
   }

   public function list () : array
   {
      $registryItem = $this->cache->getItem ('wisp:api_key:registry');

      if (!$registryItem->isHit ()) {
         return [];
      }

      $registry = $registryItem->get () ?? [];
      $keys = [];

      foreach ($registry as $hashedKey => $metadata) {
         $item = $this->cache->getItem ("wisp:api_key:{$hashedKey}");

         if ($item->isHit ()) {
            $keys [] = $item->get ();
         } else {
            $this->removeFromRegistry ($hashedKey);
         }
      }

      return $keys;
   }

   private function addToRegistry (string $hashedKey, array $data) : void
   {
      $registryItem = $this->cache->getItem ('wisp:api_key:registry');
      $registry = $registryItem->isHit () ? $registryItem->get () : [];

      $registry [$hashedKey] = [
         'user_id' => $data ['user_id'],
         'created_at' => $data ['created_at']
      ];

      $registryItem->set ($registry);
      $this->cache->save ($registryItem);
   }

   private function removeFromRegistry (string $hashedKey) : void
   {
      $registryItem = $this->cache->getItem ('wisp:api_key:registry');

      if (!$registryItem->isHit ()) {
         return;
      }

      $registry = $registryItem->get ();
      unset ($registry [$hashedKey]);

      $registryItem->set ($registry);
      $this->cache->save ($registryItem);
   }
}
