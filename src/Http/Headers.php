<?php

namespace Wisp\Http;

use ArrayAccess;
use ArrayIterator;
use IteratorAggregate;
use Traversable;

class Headers implements ArrayAccess, IteratorAggregate
{
   public array $headers = [];

   public function getIterator () : Traversable
   {
      return new ArrayIterator ($this->toArray ());
   }

   public function has (string $name, ?string $value) 
   {
      foreach ($this->headers as $header) {
         if ($header->is ($name)) {
            if ($value) {
               if ($header->value === $value) {
                  return true;
               }
            } else {
               return true;
            }
         }
      }

      return false;
   }

   public function offsetExists (mixed $offset) : bool
   {  
      foreach ($this->headers as $header) {
         if ($header->is ($offset)) {
            return true;
         }
      }

      return false;
   }

   public function offsetGet (mixed $key) : mixed
   {
      $matches = [];

      foreach ($this->headers as $header) {
         if ($header->is ($key)) {
            $matches [] = $header->value;
         }
      }

      if (empty ($matches)) {
         return null;
      }

      return implode (', ', $matches);
   }

   public function offsetSet (mixed $key, mixed $value) : void
   {
      $header = new Header ();
      $header->name = $key;
      $header->value = $value;

      $this->headers [$header->name] = $header;
   }

   public function offsetUnset (mixed $offset) : void
   {
      for ($i = 0; $i < count ($this->headers); $i++) {
         if ($this->headers [$i]->is ($offset)) {
            array_splice ($this->headers, $i--, 1);
         }
      }
   }

   public function toArray () : array
   {
      $headers = [];

      foreach ($this->headers as $header) {
         if (isset ($headers [$header->name])) {
            $headers [$header->name] .= ', ' . $header->value;
         } else {
            $headers [$header->name] = $header->value;
         }
      }

      return $headers;
   }
}