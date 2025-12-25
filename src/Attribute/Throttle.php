<?php

namespace Wisp\Attribute;

use Attribute;

#[Attribute (Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Throttle
{
   /** @var string[] */
   public readonly array $for;

   /**
    * @param int $limit Rate limit (0 = unlimited)
    * @param int $interval Time window in seconds
    * @param string $strategy Key strategy: ip, user, ip_user, route, ip_route
    * @param string|null $id Custom limiter ID
    * @param string|string[]|null $for Role(s) this limit applies to (null = default/fallback)
    */
   public function __construct (
      public readonly int $limit = 60,
      public readonly int $interval = 60,
      public readonly string $strategy = 'ip',
      public readonly ?string $id = null,
      string|array|null $for = null
   )
   {
      if ($for === null) {
         $this->for = [];
      } elseif (is_string ($for)) {
         $this->for = [ $for ];
      } else {
         $this->for = $for;
      }
   }

   public function isDefault (): bool
   {
      return empty ($this->for);
   }

   public function isUnlimited (): bool
   {
      return $this->limit === 0;
   }
}
