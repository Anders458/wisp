<?php

namespace Wisp\Attribute;

use Attribute;

#[Attribute (Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Before
{
   /** @var string[] */
   public readonly array $only;

   /** @var string[] */
   public readonly array $except;

   /**
    * @param string|string[]|null $only Only apply to these action methods
    * @param string|string[]|null $except Exclude these action methods
    */
   public function __construct (
      string|array|null $only = null,
      string|array|null $except = null
   )
   {
      if ($only === null) {
         $this->only = [];
      } elseif (is_string ($only)) {
         $this->only = [ $only ];
      } else {
         $this->only = $only;
      }

      if ($except === null) {
         $this->except = [];
      } elseif (is_string ($except)) {
         $this->except = [ $except ];
      } else {
         $this->except = $except;
      }
   }

   public function appliesTo (string $method): bool
   {
      if (!empty ($this->only)) {
         return in_array ($method, $this->only, true);
      }

      if (!empty ($this->except)) {
         return !in_array ($method, $this->except, true);
      }

      return true;
   }
}
