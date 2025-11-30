<?php

namespace Wisp\Exception;

class ServiceResolutionException extends \RuntimeException
{
   public static function classNotFound (string $class) : self
   {
      return new self ("Deferred callable resolution failed: class '{$class}' does not exist");
   }

   public static function methodNotFound (string $class, string $method) : self
   {
      return new self ("Method '{$method}' does not exist on class '{$class}'");
   }
}
