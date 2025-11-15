<?php

namespace Wisp\Session;

use Psr\Cache\CacheItemPoolInterface;

class CacheSessionHandler implements \SessionHandlerInterface
{
   public function __construct (
      private CacheItemPoolInterface $cache,
      private int $ttl = 604800,
      private string $prefix = 'wisp:session:'
   )
   {
   }

   public function close () : bool
   {
      return true;
   }

   public function destroy (string $id) : bool
   {
      return $this->cache->deleteItem ($this->prefix . $id);
   }

   public function gc (int $max_lifetime) : int | false
   {
      // PSR-6 cache handles expiration automatically
      return 0;
   }

   public function open (string $path, string $name) : bool
   {
      return true;
   }

   public function read (string $id) : string | false
   {
      $item = $this->cache->getItem ($this->prefix . $id);

      if ($item->isHit ()) {
         $data = $item->get ();
         return is_string ($data) ? $data : '';
      }

      return '';
   }

   public function write (string $id, string $data) : bool
   {
      $item = $this->cache->getItem ($this->prefix . $id);
      $item->set ($data);
      $item->expiresAfter ($this->ttl);

      return $this->cache->save ($item);
   }
}
