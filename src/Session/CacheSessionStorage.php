<?php

namespace Wisp\Session;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\HttpFoundation\Session\Storage\MetadataBag;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;

class CacheSessionStorage extends NativeSessionStorage
{
   public function __construct (
      private CacheItemPoolInterface $cache,
      private int $ttl = 604800,
      string $name = 'wisp_session',
      bool $secure = false,
      string $sameSite = 'lax',
      ?MetadataBag $metaBag = null
   )
   {
      // Use NativeSessionStorage with custom handler
      $handler = new CacheSessionHandler ($cache, $ttl);

      parent::__construct (
         [
            'name' => $name,
            'cookie_lifetime' => $ttl,
            'cookie_path' => '/',
            'cookie_secure' => $secure,
            'cookie_httponly' => true,
            'cookie_samesite' => $sameSite,
            'gc_maxlifetime' => $ttl,
            'use_cookies' => 1,           // Let PHP handle cookie automatically
            'use_only_cookies' => 1,      // Security: Only use cookies (not URL params)
            'use_trans_sid' => 0,         // Security: Don't append session ID to URLs
         ],
         $handler,
         $metaBag
      );
   }
}
