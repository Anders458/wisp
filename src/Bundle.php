<?php

namespace Wisp;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class Bundle extends AbstractBundle
{
   protected string $extensionAlias = 'wisp';

   public function configure (DefinitionConfigurator $definition): void
   {
      $definition->rootNode ()
         ->children ()
            ->scalarNode ('version')->defaultValue ('1.0.0')->end ()
            ->arrayNode ('envelope')
               ->canBeEnabled ()
               ->children ()
                  ->booleanNode ('include_debug_info')->defaultTrue ()->end ()
               ->end ()
            ->end ()
            ->arrayNode ('throttle')
               ->canBeEnabled ()
               ->children ()
                  ->integerNode ('limit')->defaultValue (60)->end ()
                  ->integerNode ('interval')->defaultValue (60)->end ()
                  ->enumNode ('strategy')
                     ->values ([ 'ip', 'user', 'ip_user', 'route', 'ip_route' ])
                     ->defaultValue ('ip')
                  ->end ()
               ->end ()
            ->end ()
         ->end ()
      ;
   }

   public function loadExtension (array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
   {
      $container->import ('../config/services.php');

      $container->parameters ()
         ->set ('wisp.version', $config ['version'])
         ->set ('wisp.envelope.enabled', $config ['envelope'] ['enabled'])
         ->set ('wisp.envelope.include_debug_info', $config ['envelope'] ['include_debug_info'])
         ->set ('wisp.throttle.enabled', $config ['throttle'] ['enabled'])
         ->set ('wisp.throttle.limit', $config ['throttle'] ['limit'])
         ->set ('wisp.throttle.interval', $config ['throttle'] ['interval'])
         ->set ('wisp.throttle.strategy', $config ['throttle'] ['strategy'])
      ;
   }
}
