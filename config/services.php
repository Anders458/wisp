<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Wisp\EventSubscriber\EnvelopeSubscriber;
use Wisp\EventSubscriber\GuardSubscriber;
use Wisp\EventSubscriber\ThrottleSubscriber;
use Wisp\EventSubscriber\ValidationExceptionSubscriber;
use Wisp\Runtime;
use Wisp\RuntimeFactory;
use Wisp\Service\Flash;
use Wisp\ValueResolver\RequestResolver;
use Wisp\ValueResolver\ValidatedDtoResolver;

return function (ContainerConfigurator $container): void {
   $services = $container->services ()
      ->defaults ()
         ->autowire ()
         ->autoconfigure ();

   // Runtime Factory
   $services->set (RuntimeFactory::class)
      ->args ([
         '%wisp.runtime.version%',
         '%wisp.runtime.default_stage%',
         '%wisp.runtime.default_debug%',
         '%wisp.runtime.detect_stage_from_cli%',
         '%wisp.runtime.detect_debug_from_cli%',
         '%wisp.runtime.hostname_map%',
         '%wisp.runtime.debug_query.enabled%',
         '%wisp.runtime.debug_query.secret%',
         '%wisp.runtime.debug_query.allowed_stages%'
      ]);

   // Runtime
   $services->set (Runtime::class)
      ->factory ([ service (RuntimeFactory::class), 'create' ]);

   // Flash Service
   $services->set (Flash::class);

   // Guard Subscriber
   $services->set (GuardSubscriber::class)
      ->tag ('kernel.event_subscriber');

   // Envelope Subscriber
   $services->set (EnvelopeSubscriber::class)
      ->args ([
         service (Runtime::class),
         service (Flash::class),
         '%wisp.envelope.enabled%',
         '%wisp.envelope.include_debug_info%'
      ])
      ->tag ('kernel.event_subscriber');

   // Throttle Subscriber
   $services->set (ThrottleSubscriber::class)
      ->args ([
         service ('cache.app'),
         service ('security.token_storage'),
         '%wisp.throttle.enabled%',
         '%wisp.throttle.limit%',
         '%wisp.throttle.interval%',
         '%wisp.throttle.strategy%'
      ])
      ->tag ('kernel.event_subscriber');

   // Request Resolver (Wisp\Http\Request type-hint)
   $services->set (RequestResolver::class)
      ->tag ('controller.argument_value_resolver', [ 'priority' => 200 ]);

   // Validated DTO Resolver
   $services->set (ValidatedDtoResolver::class)
      ->tag ('controller.argument_value_resolver', [ 'priority' => 150 ]);

   // Validation Exception Subscriber
   $services->set (ValidationExceptionSubscriber::class)
      ->tag ('kernel.event_subscriber');
};
