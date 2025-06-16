<?php

namespace Wisp;

use Exception;
use ReflectionClass;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;

class Container
{
   private static $default;

   private array $bindings = [];
   private array $cache = [];
   private array $transient = [];

   public function __construct ()
   {
      $this->bind (self::class, fn () => $this);
   }

   public function bind (string $id, callable $resolver) : self
   {
      $this->bindings [$id] = $resolver;
      return $this;
   }

   public function factory (string $class) : mixed
   {
      $reflector = new ReflectionClass ($class);

      $properties = [];

      do {
         $properties = [
            ... $properties,
            ... $reflector->getProperties ()
         ];
      } while ($reflector = $reflector->getParentClass ());
   
      $instance = new $class;

      foreach ($properties as $property) {
         $type = $property->getType ();

         if ($type) {
            $property->setAccessible (true);
            $property->setValue ($instance, $this->resolve ($type->getName ()));
         }
      }

      return $instance;
   }

   public static function get () : self
   {
      if (!isset (self::$default)) {
         self::$default = new self ();
      }

      return self::$default;
   }

   public function resolve (string $id) : mixed
   {
      if (isset ($this->bindings [$id])) {
         if (isset ($this->cache [$id])) {
            return $this->cache [$id];
         }

         $singleton = $this->bindings [$id] ();

         $this->cache [$id] = $singleton;         
         
         return $singleton;
      }

      if (!isset ($this->transient [$id])) {
         throw new Exception ('Cannot resolve unregistered binding: ' . $id);
      }
      
      return $this->transient [$id] ();
   }

   public function resolveAll (array $dependencies) : mixed
   {
      $resolved = [];

      foreach ($dependencies as $key => $id) {
         $resolved [$key] = $this->resolve ($id);
      }

      return $resolved;
   }

   public function resolveDependencies (ReflectionFunctionAbstract $reflector) : array
   {
      $dependencies = [];

      foreach ($reflector->getParameters () as $parameter) {
         $type = $parameter->getType ();

         if (!$type || $type->isBuiltin ()) {
            if ($parameter->isDefaultValueAvailable ()) {
               $dependencies [] = $parameter->getDefaultValue ();
            } else {
               throw new Exception ('Cannot resolve parameter without type hint or default value: ' . $parameter->getName ());
            }
         } else {
            $dependencies [] = $this->resolve ($type->getName ());
         }
      }

      return $dependencies;
   }

   public function run (Invokable $invokable) : mixed
   {
      $callable = $invokable->getCallable ();

      if ($invokable->isFunction ()) {
         $reflector = new ReflectionFunction ($callable);
         return $callable (... $this->resolveDependencies ($reflector));
      }

      if ($invokable->isMagic ()) {
         $reflector = new ReflectionMethod ($callable, '__invoke');
         return $callable (... $this->resolveDependencies ($reflector));
      }

      if ($invokable->isTuple ()) {
         [ $class, $method ] = $callable;
         $controller = is_object ($class) ? $class : $this->factory ($class);
      } else if ($invokable->isObject ()) {
         $controller = $callable;
      }

      $reflector = new ReflectionMethod ($controller, $method);
      return $controller->{$method} (... $this->resolveDependencies ($reflector));
   }

   public function transient (string $id, callable $resolver) : self
   {
      $this->transient [$id] = $resolver;
      return $this;
   }
}