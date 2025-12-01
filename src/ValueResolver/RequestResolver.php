<?php

namespace Wisp\ValueResolver;

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Wisp\Http\Request;

class RequestResolver implements ValueResolverInterface
{
   public function resolve (SymfonyRequest $request, ArgumentMetadata $argument): iterable
   {
      if ($argument->getType () !== Request::class) {
         return [];
      }

      // Create our Request from the Symfony request
      yield Request::createFrom ($request);
   }
}
