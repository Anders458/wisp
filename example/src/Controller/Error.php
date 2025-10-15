<?php

namespace Wisp\Example\Controller;

use Symfony\Component\EventDispatcher\EventDispatcher;
use Wisp\Http\Request;
use Wisp\Http\Response;

class Error
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

   public function internalError ()
   {
      return $this->response
         ->status (500)
         ->json ([
            'error' => 'Internal Server Error',
            'message' => 'An unexpected error occurred'
         ]);
   }

   public function notFound ()
   {
      die ('t1'); 
      return $this->response
         ->status (404)
         ->json ([
            'error' => 'Not Found',
            'message' => 'The requested resource was not found'
         ]);
   }
}