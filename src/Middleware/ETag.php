<?php

namespace Wisp\Middleware;

use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Wisp\Http\Request;

class ETag
{
   public function __construct (
      private Request $request,
      private SymfonyResponse $response,
   )
   {
   }

   public function after () : void
   {
      if (! ($etag = $this->response->headers->get ('ETag'))) {
         return;
      }

      if ($this->request->headers->get ('If-None-Match') === $etag) {
         $this->response->setStatusCode (304);
         $this->response->setContent (null);
      }
   }
}
