<?php

namespace Wisp\Attribute;

use Attribute;

#[Attribute (Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Throttle
{
   public function __construct (
      public readonly int $limit = 60,
      public readonly int $interval = 60,
      public readonly string $strategy = 'ip',
      public readonly ?string $id = null
   )
   {
   }
}
