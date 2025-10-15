<?php

namespace Wisp\Example\Controller;

use Symfony\Component\EventDispatcher\EventDispatcher;
use Wisp\Http\Request;
use Wisp\Http\Response;

class System
{
   public function __construct (
      protected Request $request,
      protected Response $response,
      protected EventDispatcher $dispatcher
   )
   {
   }

   public function before ()
   {
      // This runs automatically before every controller action
      error_log ('BEFORE HOOK: Request URI: ' . $this->request->getRequestUri ());
   }

   public function after ()
   {
      // This runs automatically after every controller action
      error_log ('AFTER HOOK: Response status: ' . $this->response->getStatusCode ());
   }

   public function forward ()
   {
      return $this->request->forward ([ System::class, 'healthCheck' ]);
   }

   public function healthCheck ()
   {
      return $this->response
         ->status (200)
         ->body ('Health check');
   }
}