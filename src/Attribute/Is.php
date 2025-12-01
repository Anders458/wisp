<?php

namespace Wisp\Attribute;

use Attribute;

#[Attribute (Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Is
{
   /** @var string[] */
   public readonly array $roles;

   public function __construct (string ...$roles)
   {
      $this->roles = $roles;
   }
}
