<?php

namespace Wisp\Attribute;

use Attribute;

#[Attribute (Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Can
{
   public function __construct (
      public readonly string $permission,
      public readonly ?string $subject = null
   )
   {
   }
}
