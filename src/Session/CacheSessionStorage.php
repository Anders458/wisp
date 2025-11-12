<?php

namespace Wisp\Session;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\HttpFoundation\Session\Storage\MetadataBag;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

class CacheSessionStorage extends MockArraySessionStorage
{
   public function __construct (
      private CacheItemPoolInterface $cache,
      private int $ttl = 604800,
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

   protected function loadSession () : void
   {
      $item = $this->cache->getItem ("wisp:session:{$this->id}");

      if ($item->isHit ()) {
         $data = $item->get ();

         if (is_array ($data)) {
            $this->setSessionData ($data);
         }
      }
   }

   public function save () : void
   {
      if (!$this->started || $this->closed) {
         return;
      }

      if (empty ($this->id)) {
         throw new \RuntimeException ('Session ID must be set before saving.');
      }

      $data = [];
      foreach ($this->bags as $bag) {
         $data [$bag->getStorageKey ()] = $bag->getBag ();
      }

      $item = $this->cache->getItem ("wisp:session:{$this->id}");
      $item->set ($data);
      $item->expiresAfter ($this->ttl);
      $this->cache->save ($item);

      $this->closed = true;
      $this->started = false;
   }
}
