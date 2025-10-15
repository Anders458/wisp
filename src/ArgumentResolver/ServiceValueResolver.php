<?php

namespace Wisp\ArgumentResolver;

use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class ServiceValueResolver implements ValueResolverInterface
{
   public function __construct (
      private ContainerInterface $container
   )
   {
   }

   public function resolve (Request $request, ArgumentMetadata $argument) : iterable
   {
      $type = $argument->getType ();

      if (!$type || $argument->isVariadic ()) {
         return [];
      }

      if ($this->container->has ($type)) {
         yield $this->container->get ($type);
      }
   }
}