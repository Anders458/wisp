<?php

namespace Wisp\Middleware;

use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Wisp\Environment\RuntimeInterface;
use Wisp\Http\Request;
use Wisp\Http\Response;
use Wisp\Service\FlashInterface;

class Envelope
{
   public function __construct (
      private Request $request,
      private RuntimeInterface $runtime,
      private FlashInterface $flash
   )
   {
   }

   public function after (SymfonyResponse $response)
   {
      if (!$response instanceof Response) {
         return;
      }

      $contentType = $response->headers->get ('Content-Type', '');

      if (!str_contains ($contentType, 'application/json')) {
         return;
      }

      $envelope = [];

      $envelope ['version'] = $this->runtime->getVersion ();
      $envelope ['status'] = $response->getStatus ();
      $envelope ['code'] = $response->getStatusCode ();
      $envelope ['stage'] = $this->runtime->getStage ();
      $envelope ['debug'] = $this->runtime->isDebug ();
      $envelope ['query_time'] = round ($this->runtime->getElapsedTime (), 4);
      $envelope ['query_date'] = gmdate ('Y-m-d H:i:s');

      $envelope ['meta'] = [
         'method' => $this->request->getMethod (),
         'request' => (string) $this->request->getUri (),
         'query' => $this->request->query->all (),
         'params' => $this->request->attributes->get ('_route_params', [])
      ];

      $flashData = $this->flash->consume ();

      if (!empty ($flashData ['errors']) || !empty ($flashData ['warnings']) || $flashData ['code'] !== 0) {
         $envelope ['flash'] = $flashData;
      }

      $body = $response->getContent ();

      if (!empty ($body)) {
         $decoded = json_decode ($body, true);
         $envelope ['body'] = $decoded ?? $body;
      }

      $response->json ($envelope);
   }
}
