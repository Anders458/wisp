<?php

namespace Wisp\Session;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\HttpFoundation\Session\Storage\MetadataBag;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

class CacheSessionStorage extends MockArraySessionStorage
{
   public function __construct (
      private CacheItemPoolInterface $cache,
      private int $ttl = 604800, // 7 days
      string $name = 'wisp:session',
      ?MetadataBag $metaBag = null
   )
   {
      parent::__construct ($name, $metaBag);
   }

   public function regenerate (bool $destroy = false, ?int $lifetime = null) : bool
   {
      if (!$this->started) {
         $this->start ();
      }

      if ($destroy && $this->id) {
         $this->cache->deleteItem ("wisp:session:{$this->id}");
      }

      return parent::regenerate ($destroy, $lifetime);
   }

   public function save () : void
   {
      if (!$this->started || $this->closed) {
         return;
      }

      if (empty ($this->id)) {
         throw new \RuntimeException ('Session ID must be set before saving.');
      }

      // Collect data from all bags
      $data = [];
      foreach ($this->bags as $bag) {
         $data [$bag->getStorageKey ()] = $bag->getBag ();
      }

      // Save to cache
      $item = $this->cache->getItem ("wisp:session:{$this->id}");
      $item->set ($data);
      $item->expiresAfter ($this->ttl);
      $this->cache->save ($item);

      $this->closed = true;
      $this->started = false;
   }

   public function start () : bool
   {
      if ($this->started) {
         return true;
      }

      if (empty ($this->id)) {
         throw new \RuntimeException ('Session ID must be set before starting session.');
      }

      // Load from cache
      $item = $this->cache->getItem ("wisp:session:{$this->id}");

      if ($item->isHit ()) {
         $this->data = $item->get ();
      } else {
         $this->data = [];
      }

      // Initialize bags with loaded data
      foreach ($this->bags as $bag) {
         $key = $bag->getStorageKey ();
         $bag->initialize ($this->data [$key] ?? []);
      }

      $this->started = true;
      $this->closed = false;

      return true;
   }
}
