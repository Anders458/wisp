<?php

namespace Wisp\Middleware;

use Wisp\Http\Request;
use Wisp\Http\Response;

class Session
{
   public function __construct (array $options = [])
   {
      session_set_cookie_params (
         [
            'lifetime' => $options ['lifetime'] ?? 3600,
            'path'     => $options ['path']     ?? '',
            'domain'   => $options ['domain']   ?? '',
            'secure'   => $options ['secure']   ?? false,
            'httponly' => $options ['httponly'] ?? true,
            'samesite' => $options ['samesite'] ?? 'Lax'
         ]
      );

      session_start ();
   }

   public function before (Request $request, Response $response)
   {
      $request->session = new Cookie ();
   }

   public function after (Request $request, Response $response)
   {
   }
}

class Cookie implements \Wisp\Http\Session
{
   public function __construct ()
   {
      if (!isset ($_SESSION ['roles'])) {
         $_SESSION ['roles'] = [];
      }
      
      if (!isset ($_SESSION ['permissions'])) {
         $_SESSION ['permissions'] = [];
      }
   }
   
   public function become (string $role) : self
   {
      $_SESSION ['authenticated'] = true;

      $_SESSION ['roles'] [] = $role;
      $_SESSION ['roles'] = array_unique ($_SESSION ['roles']);
      
      return $this;
   }
   
   public function check () : bool
   {
      return !empty ($_SESSION ['authenticated']);
   }

   public function grant (string $permission) : self
   {
      $_SESSION ['authenticated'] = true;

      $_SESSION ['permissions'] [] = $permission;
      $_SESSION ['permissions'] = array_unique ($_SESSION ['permissions']);

      return $this;
   }
   
   public function is (string ...$roles) : bool
   {
      return !empty (array_intersect ($roles, $_SESSION ['roles']));
   }
   
   public function can (string ...$permissions) : bool
   {
      return !empty (array_intersect ($permissions, $_SESSION ['permissions']));
   }
   
   public function offsetExists (mixed $offset) : bool
   {
      return isset ($_SESSION [$offset]);
   }
   
   public function offsetGet (mixed $offset) : mixed
   {
      return $_SESSION [$offset] ?? null;
   }
   
   public function offsetSet (mixed $offset, mixed $value) : void
   {
      $_SESSION [$offset] = $value;
   }
   
   public function offsetUnset (mixed $offset) : void
   {
      unset ($_SESSION [$offset]);
   }

   public function toArray () : array
   {
      return $_SESSION;
   }
}