<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Wisp\Command\DebugRequestCommand;
use Wisp\Command\TestCommand;
use Wisp\EventSubscriber\CacheSubscriber;
use Wisp\EventSubscriber\EnvelopeSubscriber;
use Wisp\EventSubscriber\ExceptionSubscriber;
use Wisp\EventSubscriber\GuardSubscriber;
use Wisp\EventSubscriber\LogSubscriber;
use Wisp\EventSubscriber\RequestIdSubscriber;
use Wisp\EventSubscriber\ThrottleSubscriber;
use Wisp\EventSubscriber\VersionSubscriber;
use Wisp\EventSubscriber\HookSubscriber;
use Wisp\EventSubscriber\BearerSubscriber;
use Wisp\Routing\VersionedAttributeLoader;
use Wisp\Security\BearerDecoderInterface;
use Wisp\Service\Flash;
use Wisp\ValueResolver\RequestResolver;

return function (ContainerConfigurator $container): void {
   $services = $container->services ()
      ->defaults ()
         ->autowire ()
         ->autoconfigure ();

   // Flash Service
   $services->set (Flash::class);

   // Guard Subscriber
   $services->set (GuardSubscriber::class)
      ->tag ('kernel.event_subscriber');

   // Envelope Subscriber
   $services->set (EnvelopeSubscriber::class)
      ->args ([
         service (Flash::class),
         service ('validator'),
         '%wisp.version%',
         '%kernel.environment%',
         '%kernel.debug%',
         '%wisp.envelope.enabled%',
         '%wisp.envelope.image%',
         '%wisp.envelope.include_debug_info%',
         service (BearerDecoderInterface::class)->nullOnInvalid ()
      ])
      ->tag ('kernel.event_subscriber');

   // Throttle Subscriber
   $services->set (ThrottleSubscriber::class)
      ->args ([
         service ('cache.app'),
         service ('security.token_storage'),
         service (Flash::class),
         '%wisp.throttle.enabled%',
         '%wisp.throttle.limit%',
         '%wisp.throttle.interval%',
         '%wisp.throttle.strategy%'
      ])
      ->tag ('kernel.event_subscriber');

   // Exception Subscriber
   $services->set (ExceptionSubscriber::class)
      ->args ([
         service (Flash::class)
      ])
      ->tag ('kernel.event_subscriber');

   // Request Resolver (Wisp\Http\Request type-hint)
   $services->set (RequestResolver::class)
      ->tag ('controller.argument_value_resolver', [ 'priority' => 200 ]);

   // Request ID Subscriber
   $services->set (RequestIdSubscriber::class)
      ->tag ('kernel.event_subscriber');

   // Log Subscriber
   $services->set (LogSubscriber::class)
      ->args ([
         service ('logger')
      ])
      ->tag ('kernel.event_subscriber');

   // Test Command
   $services->set (TestCommand::class)
      ->tag ('console.command');

   // Debug Request Command
   $services->set (DebugRequestCommand::class)
      ->tag ('console.command');

   // Cache Subscriber
   $services->set (CacheSubscriber::class)
      ->tag ('kernel.event_subscriber');

   // Version Subscriber (API versioning via #[Version] attribute)
   $services->set (VersionSubscriber::class)
      ->tag ('kernel.event_subscriber');

   // Hook Subscriber (#[Before] and #[After] controller hooks)
   $services->set (HookSubscriber::class)
      ->args ([
         service ('service_container')
      ])
      ->tag ('kernel.event_subscriber');

   // Bearer Subscriber (#[Bearer] token authentication)
   $services->set (BearerSubscriber::class)
      ->args ([
         service (BearerDecoderInterface::class)->nullOnInvalid (),
         service (Flash::class)
      ])
      ->tag ('kernel.event_subscriber');

};
