<?php

namespace Wisp\Attribute;

use Attribute;

/**
 * Cache response for the specified TTL.
 *
 * Usage:
 *   #[Cached (ttl: 300)]                 // 5 min private cache
 *   #[Cached (ttl: 3600, public: true)]  // 1 hour public cache
 *   #[Cached (ttl: 60, vary: ['Accept'])] // Vary by Accept header
 *
 * Notes:
 *   - Only caches successful responses (2xx)
 *   - Only caches GET/HEAD requests
 *   - Set ttl: 0 to disable caching for a specific endpoint
 */
#[Attribute (Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Cached
{
   public function __construct (
      public int $ttl = 60,
      public bool $public = false,
      public array $vary = []
   ) {}
}
