<?php

namespace Wisp\Middleware;

use Wisp\Http\Request;
use Wisp\Http\Response;

class Cache
{
   public function __construct (
      private Response $response,
      private Request $request,
      private int $ttl = 3600,
      private bool $public = true,
      private bool $etag = true,
      private array $vary = []
   )
   {
   }

   public function after ()
   {
      $content = $this->response->getContent ();

      // Generate and check ETag
      if ($this->etag && !empty ($content)) {
         $etag = md5 ($content);
         $this->response->headers->set ('ETag', '"' . $etag . '"');

         if ($this->request->headers->get ('If-None-Match') === '"' . $etag . '"') {
            return $this->response->status (304)->setContent ('');
         }
      }

      // Set Cache-Control headers
      $cacheControl = $this->public ? 'public' : 'private';
      $cacheControl .= ', max-age=' . $this->ttl;
      $this->response->headers->set ('Cache-Control', $cacheControl);

      // Set Vary headers
      if (!empty ($this->vary)) {
         $this->response->headers->set ('Vary', implode (', ', $this->vary));
      }
   }
}
