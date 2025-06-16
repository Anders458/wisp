<?php

namespace Wisp\Http;

use ArrayAccess;
use ArrayIterator;
use Exception;
use IteratorAggregate;
use Traversable;

class CookieJar implements ArrayAccess, IteratorAggregate
{
   public array $cookies = [];

   public function getIterator () : Traversable
   {
      return new ArrayIterator ($this->cookies);
   }

   public function offsetExists (mixed $offset) : bool
   {
      return isset ($this->cookies [$offset]);
   }

   public function offsetGet (mixed $key) : mixed
   {
      if (isset ($this->cookies [$key])) {
         return $this->cookies [$key];
      }

      return null;
   }

   public function offsetSet (mixed $key, mixed $value) : void
   {
      if ($value instanceof Cookie) {
         $this->cookies [$value->name] = $value;
      } else {
         if ($key === null) {
            throw new Exception ('Cannot add a non-cookie value with an ambiguous key to the CookieJar');
         } else {
            $cookie = new Cookie ();
            $cookie->name = $key;
            $cookie->value = $value;

            $this->cookies [$key] = $cookie;
         }
      }
   }

   public function offsetUnset (mixed $offset) : void
   {
      unset ($this->cookies [$offset]);
   }

   public function toArray () : array
   {
      return $this->cookies;
   }
}