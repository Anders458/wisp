<?php

use Wisp\Container;
use Wisp\Util\Logger;

if (!function_exists ('container')) {
   function container (string $id)
   {
      return Container::get ()->resolve ($id);
   }
}

if (!function_exists ('logger')) {
   function logger ()
   {
      return container (Logger::class);
   }
}