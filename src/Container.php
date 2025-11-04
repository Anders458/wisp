<?php

namespace Wisp;

use Symfony\Component\DependencyInjection\ContainerBuilder;

class Container
{
   private static ContainerBuilder $container;

   public static function instance () : ContainerBuilder
   {
      if (!isset (self::$container)) {
         self::$container = new ContainerBuilder ();
         
         self::$container
            ->setAlias (ContainerBuilder::class, 'service_container')
            ->setPublic (true);
      }

      return self::$container;
   }
}