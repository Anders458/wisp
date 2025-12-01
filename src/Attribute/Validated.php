<?php

namespace Wisp\Attribute;

use Attribute;

#[Attribute (Attribute::TARGET_PARAMETER)]
class Validated
{
   public function __construct (
      public readonly bool $strict = true
   )
   {
   }
}
