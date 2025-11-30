<?php

namespace Wisp\Security;

use Psr\Cache\CacheItemPoolInterface;

trait CacheRegistry
{
   abstract protected function getRegistryKey () : string;

   protected function addToRegistry (CacheItemPoolInterface $cache, string $hashedKey, array $data) : void
   {
      $registryItem = $cache->getItem ($this->getRegistryKey ());
      $registry = $registryItem->isHit () ? $registryItem->get () : [];

      $registry [$hashedKey] = [
         'user_id' => $data ['user_id'],
         'created_at' => $data ['created_at']
      ];

      $registryItem->set ($registry);
      $cache->save ($registryItem);
   }

   protected function removeFromRegistry (CacheItemPoolInterface $cache, string $hashedKey) : void
   {
      $registryItem = $cache->getItem ($this->getRegistryKey ());

      if (!$registryItem->isHit ()) {
         return;
      }

      $registry = $registryItem->get ();
      unset ($registry [$hashedKey]);

      $registryItem->set ($registry);
      $cache->save ($registryItem);
   }

   protected function getRegistry (CacheItemPoolInterface $cache) : array
   {
      $registryItem = $cache->getItem ($this->getRegistryKey ());

      if (!$registryItem->isHit ()) {
         return [];
      }

      return $registryItem->get () ?? [];
   }

   public function prune () : int
   {
      $pruned = 0;
      $registry = $this->getRegistry ($this->cache);

      foreach ($registry as $hashedKey => $metadata) {
         $item = $this->cache->getItem ($this->getItemCacheKey ($hashedKey));

         if (!$item->isHit ()) {
            $this->removeFromRegistry ($this->cache, $hashedKey);
            $pruned++;
         }
      }

      return $pruned;
   }

   abstract protected function getItemCacheKey (string $hashedKey) : string;
}
