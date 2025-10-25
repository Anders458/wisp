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
      }

      return self::$container;
   }
}