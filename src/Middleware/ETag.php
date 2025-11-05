<?php

namespace Wisp\Middleware;

use Wisp\Http\Request;
use Wisp\Http\Response;

class ETag
{
   public function __construct (
      private Request $request,
      private Response $response,
   )
   {
   }

   public function after () : void
   {
      if (! ($etag = $this->request->headers->get ('ETag'))) {
         return;
      }

      if ($this->request->headers->get ('If-None-Match') === $etag) {
         $this->response->setStatusCode (304);
         $this->response->setContent (null);
      }
   }
}
