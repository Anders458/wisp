<?php

namespace Wisp\Util;

use ArrayAccess;
use ArrayIterator;
use IteratorAggregate;
use Traversable;

class Config implements ArrayAccess, IteratorAggregate
{
   protected array $settings = [];

   public function expand (array $array) : array
   {
      $expanded = [];

      foreach ($array as $key => $value) {
         $keys = explode ('.', $key);

         $current = &$expanded;

         foreach ($keys as $innerKey) {
            if (!isset ($current [$innerKey]) || !is_array ($current [$innerKey])) {
               $current [$innerKey] = [];
            }

            $current = &$current [$innerKey];
         }

         $current = $value;
      }

      return $expanded;
   } 

   public function get (string $path, mixed $default = null) : mixed
   {
      $value = $this->settings;

      foreach (explode ('.', $path) as $key) {
         if (isset ($value [$key])) {
            $value = $value [$key];
         } else {
            $value = $default;
            break;
         }
      }

      return $value;
   }

   public function getIterator () : Traversable
   {
      return new ArrayIterator ($this->settings);
   }
   
   public function has (string $path) : bool
   {
      $value = $this->settings;

      foreach (explode ('.', $path) as $key) {
         if (isset ($value [$key])) {
            $value = $value [$key];
         } else {
            return false;
         }
      }

      return true;
   }

   public static function ini (string $path) : Config
   {
      $config = new Config ();

      if (is_file ($path)) {
         $ini = parse_ini_file ($path, true);
         $ini = $config->expand ($ini);

         $config->set ($ini);
      }

      return $config;
   }

   private function merge (array $x, array $y) : array
   {
      foreach ($y as $k => $v) {
         if (isset ($x [$k]) && 
             is_array ($x [$k]) &&
             is_array ($v)) {
            $x [$k] = self::merge ($x [$k], $v);
         } else {
            $x [$k] = $v;
         }
      }

      return $x;
   }

   public function offsetExists (mixed $offset) : bool
   {
      return $this->has ($offset);
   }

   public function offsetGet (mixed $key) : mixed
   {
      if ($this->has ($key)) {
         return $this->get ($key);
      }

      return null;
   }

   public function offsetSet (mixed $key, mixed $value) : void
   {
      if ($key === null) {
         $this->set ($value);
      } else {
         $this->set ($this->expand ([ $key => $value ]));
      }
   }

   public function offsetUnset (mixed $offset) : void
   {
      $pointer = &$this->settings;

      $parts = explode ('.', $offset);

      for ($i = 0, $cc = count ($parts); $i < $cc - 1; $i++) {
         $key = $parts [$i];

         if (isset ($pointer [$key])) {
            $pointer = &$pointer [$key];
         } else {
            $pointer = null;
            break;
         }
      }

      if ($pointer) {
         unset ($pointer [$parts [$cc - 1]]);
      }
   }
   
   public function set (array $settings)
   {
      $this->settings = self::merge (
         $this->settings,
         $settings
      );
   }
}