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
            ->arrayNode ('runtime')
               ->addDefaultsIfNotSet ()
               ->children ()
                  ->scalarNode ('version')->defaultValue ('1.0.0')->end ()
                  ->enumNode ('default_stage')
                     ->values ([ 'dev', 'test', 'prod' ])
                     ->defaultValue ('prod')
                  ->end ()
                  ->booleanNode ('default_debug')->defaultFalse ()->end ()
                  ->booleanNode ('detect_stage_from_cli')->defaultTrue ()->end ()
                  ->booleanNode ('detect_debug_from_cli')->defaultTrue ()->end ()
                  ->arrayNode ('hostname_map')
                     ->useAttributeAsKey ('hostname')
                     ->enumPrototype ()
                        ->values ([ 'dev', 'test', 'prod' ])
                     ->end ()
                  ->end ()
                  ->arrayNode ('debug_query')
                     ->canBeEnabled ()
                     ->children ()
                        ->scalarNode ('secret')->defaultNull ()->end ()
                        ->arrayNode ('allowed_stages')
                           ->enumPrototype ()
                              ->values ([ 'dev', 'test', 'prod' ])
                           ->end ()
                           ->defaultValue ([ 'dev', 'test' ])
                        ->end ()
                     ->end ()
                  ->end ()
               ->end ()
            ->end ()
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
         ->set ('wisp.runtime.version', $config ['runtime'] ['version'])
         ->set ('wisp.runtime.default_stage', $config ['runtime'] ['default_stage'])
         ->set ('wisp.runtime.default_debug', $config ['runtime'] ['default_debug'])
         ->set ('wisp.runtime.detect_stage_from_cli', $config ['runtime'] ['detect_stage_from_cli'])
         ->set ('wisp.runtime.detect_debug_from_cli', $config ['runtime'] ['detect_debug_from_cli'])
         ->set ('wisp.runtime.hostname_map', $config ['runtime'] ['hostname_map'])
         ->set ('wisp.runtime.debug_query.enabled', $config ['runtime'] ['debug_query'] ['enabled'])
         ->set ('wisp.runtime.debug_query.secret', $config ['runtime'] ['debug_query'] ['secret'] ?? null)
         ->set ('wisp.runtime.debug_query.allowed_stages', $config ['runtime'] ['debug_query'] ['allowed_stages'] ?? [])
         ->set ('wisp.envelope.enabled', $config ['envelope'] ['enabled'])
         ->set ('wisp.envelope.include_debug_info', $config ['envelope'] ['include_debug_info'])
         ->set ('wisp.throttle.enabled', $config ['throttle'] ['enabled'])
         ->set ('wisp.throttle.limit', $config ['throttle'] ['limit'])
         ->set ('wisp.throttle.interval', $config ['throttle'] ['interval'])
         ->set ('wisp.throttle.strategy', $config ['throttle'] ['strategy'])
      ;
   }
}
