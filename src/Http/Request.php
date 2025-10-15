<?php

namespace Wisp\Http;

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Wisp\Wisp;

class Request extends SymfonyRequest
{
   public function forward (array $controller, array $attributes = []) : Response
   {
      $kernel = Wisp::container ()->get (HttpKernelInterface::class);

      $attributes ['_controller'] = $controller;

      $subRequest = $this->duplicate (
         null, 
         null, 
         $attributes
      );

      $response = $kernel->handle ($subRequest, HttpKernelInterface::SUB_REQUEST);

      return $response;
   }
}
