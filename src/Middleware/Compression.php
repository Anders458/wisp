<?php

namespace Wisp\Middleware;

use Wisp\Http\Request;
use Wisp\Http\Response;

class Compression
{
   public function __construct (
      private Response $response,
      private Request $request,
      private int $threshold = 1024,
      private int $level = 6,
      private array $skipTypes = [ 'image/', 'video/' ]
   )
   {
   }

   public function after ()
   {
      $content = $this->response->getContent ();
      $contentType = $this->response->headers->get ('Content-Type', '');

      // Skip if content is too small
      if (strlen ($content) < $this->threshold) {
         return;
      }

      // Skip certain content types
      foreach ($this->skipTypes as $type) {
         if (str_contains ($contentType, $type)) {
            return;
         }
      }

      // Check if client accepts gzip
      $encoding = $this->request->headers->get ('Accept-Encoding', '');

      if (str_contains ($encoding, 'gzip')) {
         $compressed = gzencode ($content, $this->level);
         $this->response->setContent ($compressed);
         $this->response->headers->set ('Content-Encoding', 'gzip');
         $this->response->headers->set ('Vary', 'Accept-Encoding');
         $this->response->headers->set ('Content-Length', (string) strlen ($compressed));
      }
   }
}
