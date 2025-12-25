<?php

namespace Wisp\Attribute;

use Attribute;

#[Attribute (Attribute::TARGET_CLASS)]
class Version
{
   public function __construct (
      public readonly string $prefix
   )
   {
   }
}
