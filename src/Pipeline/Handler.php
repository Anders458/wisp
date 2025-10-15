<?php

namespace Wisp\Pipeline;

use Closure;
use Wisp\Invocable;

class Handler
{
   public function __construct (
      public Closure  $action,
      public Priority $priority
   ) 
   {
   }
}