<?php

namespace Wisp\Service;

class Flash
{
   public array $errors = [];
   public array $warnings = [];
   public int $code = 0;

   public function error (string $message, ?int $code = null) : self
   {
      $this->errors [] = $message;
      
      if ($code !== null && $code !== 0) {
         $this->code |= $code;
      }
      
      return $this;
   }

   public function warning (string $message, ?int $code = null) : self
   {
      $this->warnings [] = $message;
      
      if ($code !== null && $code !== 0) {
         $this->code |= $code;
      }
      
      return $this;
   }

   public function clear () : self
   {
      $this->errors = [];
      $this->warnings = [];
      $this->code = 0;
      
      return $this;
   }
}