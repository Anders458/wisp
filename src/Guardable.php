<?php

namespace Wisp;

trait Guardable
{
   public array $roles = [];
   public array $permissions = [];

   public function can (string | array $permissions) : self
   {
      $this->permissions = is_array ($permissions) ? $permissions : [ $permissions ];
      return $this;
   }

   public function is (string | array $roles) : self
   {
      $this->roles = is_array ($roles) ? $roles : [ $roles ];
      return $this;
   }
}
