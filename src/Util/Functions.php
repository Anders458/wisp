<?php

use Wisp\Container;

if (!function_exists ('container')) {
   function container (string $id)
   {
      return Container::get ()->resolve ($id);
   }
}