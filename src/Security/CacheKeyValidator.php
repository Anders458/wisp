<?php

namespace Wisp\Security;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Wisp\Contracts\KeyValidatorInterface;

class CacheKeyValidator implements KeyValidatorInterface
{
   use CacheRegistry;

   public function __construct (
      private CacheItemPoolInterface $cache
   )
   {
   }

   protected function getRegistryKey () : string
   {
      return 'wisp:api_key:registry';
   }

   protected function getItemCacheKey (string $hashedKey) : string
   {
      return "wisp:api_key:{$hashedKey}";
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

      $this->addToRegistry ($this->cache, $hashedKey, $data);
   }

   public function revoke (string $key) : bool
   {
      $hashedKey = hash ('sha256', $key);
      $cacheKey = "wisp:api_key:{$hashedKey}";

      $deleted = $this->cache->deleteItem ($cacheKey);

      if ($deleted) {
         $this->removeFromRegistry ($this->cache, $hashedKey);
      }

      return $deleted;
   }

   public function list () : array
   {
      $registry = $this->getRegistry ($this->cache);
      $keys = [];

      foreach ($registry as $hashedKey => $metadata) {
         $item = $this->cache->getItem ("wisp:api_key:{$hashedKey}");

         if ($item->isHit ()) {
            $keys [] = $item->get ();
         } else {
            $this->removeFromRegistry ($this->cache, $hashedKey);
         }
      }

      return $keys;
   }
}
