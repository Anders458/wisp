<?php

namespace Wisp\Middleware;

use Wisp\Environment\Runtime;
use Wisp\Http\Request;
use Wisp\Http\Response;
use Wisp\Service\Flash;

class Envelope
{
   public function __construct (
      private Request $request,
      private Response $response,
      private Runtime $runtime,
      private Flash $flash,
      private ?CSRF $csrf = null
   )
   {
   }

   public function after (Response $response)
   {
      // Only wrap JSON responses
      $contentType = $response->headers->get ('Content-Type', '');

      if (!str_contains ($contentType, 'application/json')) {
         return;
      }

      $envelope = [];

      $envelope ['version'] = $this->runtime->getVersion ();
      $envelope ['status'] = $this->response->getStatus ();
      $envelope ['code'] = $this->response->getStatusCode ();
      $envelope ['stage'] = $this->runtime->getStage ();
      $envelope ['debug'] = $this->runtime->isDebug ();
      $envelope ['query_time'] = round ($this->runtime->getElapsedTime (), 4);
      $envelope ['query_date'] = gmdate ('Y-m-d H:i:s');

      // Include CSRF token if CSRF middleware is registered
      if ($this->csrf !== null) {
         $envelope ['csrf'] = $this->csrf->getToken ();
      }

      $envelope ['meta'] = [
         'method' => $this->request->getMethod (),
         'request' => (string) $this->request->getUri (),
         'query' => $this->request->query->all (),
         'params' => $this->request->attributes->get ('_route_params', [])
      ];

      if (!empty ($this->flash->errors) || !empty ($this->flash->warnings) || $this->flash->code !== 0) {
         $envelope ['flash'] = [
            'errors' => $this->flash->errors,
            'warnings' => $this->flash->warnings,
            'code' => $this->flash->code
         ];
      }

      $body = $this->response->getContent ();

      if (!empty ($body)) {
         $decoded = json_decode ($body, true);
         $envelope ['body'] = $decoded ?? $body;
      } else {
         $envelope ['body'] = null;
      }

      $this->response->json ($envelope);
   }
}
