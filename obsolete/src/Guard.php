<?php

namespace Wisp;

use Wisp\Http\Request;
use Wisp\Http\Response;
use Wisp\Util\Logger;

class Guard
{
   private RouteGroup | Route $parent;
   private array $roles;
   private array $permissions;

   public function __construct (RouteGroup | Route $parent)
   {
      $this->parent = $parent;
      $this->roles = [];
      $this->permissions = [];
   }

   public function can (array $permissions) : self
   {
      $this->permissions = array_merge ($this->permissions, $permissions);
      return $this;
   }

   public function is (array $roles) : self
   {
      $this->roles = array_merge ($this->roles, $roles);
      return $this;
   }

   public function __call (string $method, array $args) : RouteGroup | Route
   {
      return $this->parent->$method (... $args);
   }

   public function __invoke (Request $request, Response $response)
   {
      if ($response->status === 401 ||
          $response->status === 403) {
         // Another guard already failed.
         return;
      }

      if (!isset ($request->session)) {
         throw new \Exception ('Session middleware is required to use guards');
      }

      $logger = container (Logger::class);

      $logger->debug ('[Wisp] [Guard] Checking to see if the request is authenticated', [
         'session' => $request->session->toArray (),
         'roles' => $this->roles,
         'permissions' => $this->permissions
      ]);

      // ->check to see if the response is authenticated
      // ->is and ->can to see if the response is authorized

      if (!$request->session->check ()) {
         $logger->debug ('[Wisp] [Guard] Request is not authenticated');
         
         $response
            ->status (401);
            
         return;
      }

      $logger->debug ('[Wisp] [Guard] Checking to see if the request is authorized');

      $authorized = false;

      if (empty ($this->roles) &&
          empty ($this->permissions)) {
         $authorized = true;
      }

      if (!empty ($this->roles)) {
         foreach ($this->roles as $role) {
            if ($request->session->is ($role)) {
               $authorized = true;
               break;
            }
         }
      }

      if (!empty ($this->permissions)) {
         foreach ($this->permissions as $permission) {
            if ($request->session->can ($permission)) {
               $authorized = true;
               return;
            }
         }
      }

      if (!$authorized) {
         $logger->debug ('[Wisp] [Guard] The request is not authorized');

         $response
            ->status (403);

         return;
      }

      $logger->debug ('[Wisp] [Guard] The request is authorized and authenticated');
   }
}