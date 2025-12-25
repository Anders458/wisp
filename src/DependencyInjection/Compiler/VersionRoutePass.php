<?php

namespace Wisp\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Wisp\Routing\VersionRouteListener;

class VersionRoutePass implements CompilerPassInterface
{
   public function process (ContainerBuilder $container): void
   {
      // Register the version route listener
      if (!$container->hasDefinition (VersionRouteListener::class)) {
         $container->register (VersionRouteListener::class, VersionRouteListener::class)
            ->setAutowired (true)
            ->addTag ('kernel.event_subscriber');
      }
   }
}
