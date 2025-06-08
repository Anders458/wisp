<?php

namespace Wisp\Http;

class Header
{
   public string $name;
   public string $value;

   public function is (string $name) : bool
   {
      return strcasecmp ($this->name, $name) === 0;
   }
}