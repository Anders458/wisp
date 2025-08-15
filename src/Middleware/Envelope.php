<?php

namespace Wisp\Middleware;

use Wisp\Environment\Runtime;
use Wisp\Http\Request;
use Wisp\Http\Response;
use Wisp\Service\Flash;

class Envelope
{
   public function before (Response $response)
   {
      $response->headers ['Content-Type'] = 'application/json';
   }

   public function after (Request $request, Response $response, Runtime $runtime, Flash $flash)
   {
      $envelope = [];

      $envelope ['version'] = $runtime->getVersion ();
      $envelope ['status'] = $response->status;
      $envelope ['code'] = $response->code;
      $envelope ['stage'] = $runtime->getStage ();
      $envelope ['debug'] = $runtime->isDebug ();
      $envelope ['query_time'] = round ($runtime->elapsed (), 4);
      $envelope ['query_date'] = gmdate ('Y-m-d H:i:s');

      $envelope ['meta'] = [
         'method' => $request->method,
         'request' => (string) $request->url,
         'query' => $request->url->query,
         'params' => $request->params
      ];

      if (!empty ($flash->errors) || !empty ($flash->warnings) || $flash->code !== 0) {
         $envelope ['flash'] = [
            'errors' => $flash->errors,
            'warnings' => $flash->warnings,
            'code' => $flash->code
         ];
      }

      $body = $response->body;

      $envelope ['body'] = $body;
      $response->body = $envelope;
   }
}