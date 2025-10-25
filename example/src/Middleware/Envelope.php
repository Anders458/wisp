<?php

namespace Wisp\Example\Middleware;

use Psr\Log\LoggerInterface;
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
      private LoggerInterface $logger
   )
   {
   }

   public function before ()
   {
      $this->logger->debug ('Middleware: Envelope before');
      $this->response->headers->set ('Content-Type', 'application/json');
   }

   public function after (Response $response)
   {
      $envelope = [];

      $envelope ['version'] = $this->runtime->getVersion ();
      $envelope ['status'] = $this->response->getStatus ();
      $envelope ['code'] = $this->response->getStatusCode ();
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

      if (!empty ($this->flash->errors) || !empty ($this->flash->warnings) || $this->flash->code !== 0) {
         $envelope ['flash'] = [
            'errors' => $this->flash->errors,
            'warnings' => $this->flash->warnings,
            'code' => $this->flash->code
         ];
      }

      $body = $this->response->getContent ();

      $envelope ['body'] = $body;
      $this->response->json ($envelope);
   }
}