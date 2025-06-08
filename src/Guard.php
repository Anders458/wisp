<?php

namespace Wisp;

use Wisp\Http\Request;
use Wisp\Http\Response;

class Guard
{
   private Route | RouteGroup $parent;
   private string $name;
   private array $roles;
   private array $permissions;

   public function __construct (Route | RouteGroup $parent, string $name)
   {
      $this->parent = $parent;
      $this->name = $name;
      $this->roles = [];
      $this->permissions = [];
   }

   public function can (string ...$permissions) : self
   {
      $this->permissions [] = $permissions;
      return $this;
   }

   public function role (string ...$roles) : self
   {
      $this->roles [] = $roles;
      return $this;
   }

   public function __call (string $method, array $args) 
   {
      return $this->parent->$method (... $args);
   }

   public function __invoke (Request $request, Response $response)
   {
      if ($response->status () === 401 ||
          $response->status () === 403) {
         // Another guard already failed.
         return;
      }

      $guard = Wisp::config () ["guards.{$this->name}"];

      // ->check to see if the response is authenticated
      // ->is and ->can to see if the response is authorized

      if (!$guard->check ($request)) {
         $response
            ->status (401);
         
         return;
      }

      foreach ($this->roles as $roles) {
         if (!$guard->is ($request, $roles)) {
            $response
               ->status (403);

            return;
         }
      }

      foreach ($this->permissions as $permissions) {
         if (!$guard->can ($request, $permissions)) {
            $response
               ->status (403);

            return;
         }
      }
   }
}