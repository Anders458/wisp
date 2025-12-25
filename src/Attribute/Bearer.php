<?php

namespace Wisp\Attribute;

use Attribute;

#[Attribute (Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Bearer
{
   /** @var array<string, mixed> */
   public readonly array $claims;

   /**
    * @param array<string, mixed> $claims Required claims and their expected values
    */
   public function __construct (
      array $claims = []
   )
   {
      $this->claims = $claims;
   }
}
