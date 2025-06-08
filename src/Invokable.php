<?php

namespace Wisp;

use Closure;
use Exception;

enum Type
{
   // Regular function
   case Function;

   // [ class | object, method ] Tuple
   case Tuple;

   // Already instantiated object
   case Object; 

   // Object which implements __invoke
   case Magic;
}

class Invokable
{
   public function __construct (
      private mixed $callable,
      private Type $type
   ) {}

   public static function from (callable | array | object $callable) : self
   {
      if ($callable instanceof Invokable) {
         return $callable;
      }
      
      if ($callable instanceof Closure) {
         return new self ($callable, Type::Function);
      }

      if (is_object ($callable)) {
         return new self ($callable, method_exists ($callable, '__invoke') ? Type::Magic : Type::Object);
      }

      if (is_callable ($callable) && !is_array ($callable)) {
         return new self ($callable, Type::Function);
      }

      if (is_array ($callable)) {
         if (count ($callable) !== 2) {
            throw new Exception ('Invokable tuple must have exactly 2 elements when passed as an array');
         }

         if (!is_object ($callable [0]) && 
             !class_exists ($callable [0])) {
            throw new Exception ('First element of invokable tuple must be an object or class');
         }

         if (!is_string ($callable [1]) ||
             !method_exists ($callable [0], $callable [1])) {
            throw new Exception ('Second element of invokable tuple must be a method of the first element');
         }

         return new self ($callable, Type::Tuple);
      }

      throw new Exception ('Tried to create non-invokable structure');
   }

   public function getType () : Type
   {
      return $this->type;
   }

   public function getCallable () : mixed
   {
      return $this->callable;
   }

   public function hasMethod (string $method) : bool
   {
      if ($this->isFunction ()) {
         return false;
      }

      return method_exists ($this->callable [0], $method);
   }

   public function isFunction () : bool
   {
      return $this->type === Type::Function;
   }

   public function isTuple () : bool
   {
      return $this->type === Type::Tuple;
   }

   public function isObject () : bool
   {
      return $this->type === Type::Object;
   }

   public function isMagic () : bool
   {
      return $this->type === Type::Magic;
   }

   public function rebind (string $method) : Invokable
   {
      return Invokable::from (
         [
            $this->callable [0],
            $method
         ]
      );
   }
}