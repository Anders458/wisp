<?php

namespace Wisp\EventSubscriber;

use Psr\Cache\CacheItemPoolInterface;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Wisp\Attribute\Throttle;
use Wisp\Service\Flash;

class ThrottleSubscriber implements EventSubscriberInterface
{
   private ?array $rateLimitInfo = null;

   public function __construct (
      private CacheItemPoolInterface $cache,
      private TokenStorageInterface $tokenStorage,
      private Flash $flash,
      private bool $enabled = true,
      private int $defaultLimit = 60,
      private int $defaultInterval = 60,
      private string $defaultStrategy = 'ip'
   )
   {
   }

   public static function getSubscribedEvents (): array
   {
      return [
         KernelEvents::CONTROLLER => [ 'onController', 20 ],
         KernelEvents::RESPONSE => [ 'onResponse', -20 ]
      ];
   }

   public function onController (ControllerEvent $event): void
   {
      if (!$this->enabled) {
         return;
      }

      if (!$event->isMainRequest ()) {
         return;
      }

      $request = $event->getRequest ();
      $throttle = $this->resolveThrottle ($event->getController ());

      if ($throttle === null) {
         // Use default throttling
         $throttle = new Throttle (
            $this->defaultLimit,
            $this->defaultInterval,
            $this->defaultStrategy
         );
      }

      $key = $this->resolveKey ($throttle, $request);
      $limiter = $this->createLimiter ($throttle);
      $limit = $limiter->create ($key)->consume ();

      $this->rateLimitInfo = [
         'limit' => $limit->getLimit (),
         'remaining' => $limit->getRemainingTokens (),
         'reset' => $limit->getRetryAfter ()?->getTimestamp ()
      ];

      if (!$limit->isAccepted ()) {
         $retryAfter = $limit->getRetryAfter ();
         $seconds = $retryAfter ? $retryAfter->getTimestamp () - time () : $throttle->interval;

         $this->rateLimitInfo ['retry_after'] = $seconds;

         $this->flash->error ('Rate limit exceeded. Please try again later.', 'rate_limit:exceeded');

         $response = new JsonResponse (null, 429);
         $response->headers->set ('Retry-After', (string) $seconds);
         $response->headers->set ('X-RateLimit-Limit', (string) $this->rateLimitInfo ['limit']);
         $response->headers->set ('X-RateLimit-Remaining', '0');
         $response->headers->set ('X-RateLimit-Reset', (string) $this->rateLimitInfo ['reset']);

         $event->setController (fn () => $response);
      }
   }

   public function onResponse (ResponseEvent $event): void
   {
      if ($this->rateLimitInfo === null) {
         return;
      }

      $response = $event->getResponse ();
      $response->headers->set ('X-RateLimit-Limit', (string) $this->rateLimitInfo ['limit']);
      $response->headers->set ('X-RateLimit-Remaining', (string) $this->rateLimitInfo ['remaining']);

      if ($this->rateLimitInfo ['reset'] !== null) {
         $response->headers->set ('X-RateLimit-Reset', (string) $this->rateLimitInfo ['reset']);
      }

      $this->rateLimitInfo = null;
   }

   private function resolveThrottle (mixed $controller): ?Throttle
   {
      if (is_array ($controller)) {
         [ $instance, $method ] = $controller;
         return $this->findThrottleAttribute ($instance::class, $method);
      }

      if (is_object ($controller) && method_exists ($controller, '__invoke')) {
         return $this->findThrottleAttribute ($controller::class, '__invoke');
      }

      return null;
   }

   private function findThrottleAttribute (string $class, string $method): ?Throttle
   {
      $methodReflection = new ReflectionMethod ($class, $method);
      $methodAttributes = $methodReflection->getAttributes (Throttle::class);

      if (!empty ($methodAttributes)) {
         return $methodAttributes [0]->newInstance ();
      }

      $classReflection = new ReflectionClass ($class);
      $classAttributes = $classReflection->getAttributes (Throttle::class);

      if (!empty ($classAttributes)) {
         return $classAttributes [0]->newInstance ();
      }

      return null;
   }

   private function createLimiter (Throttle $throttle): RateLimiterFactory
   {
      $id = $throttle->id ?? 'default';

      return new RateLimiterFactory (
         [
            'id' => "wisp:throttle:{$id}",
            'policy' => 'sliding_window',
            'limit' => $throttle->limit,
            'interval' => "{$throttle->interval} seconds"
         ],
         new CacheStorage ($this->cache)
      );
   }

   private function resolveKey (Throttle $throttle, mixed $request): string
   {
      return match ($throttle->strategy) {
         'ip' => $this->getIpKey ($request),
         'user' => $this->getUserKey (),
         'ip_user' => $this->getIpUserKey ($request),
         'route' => $this->getRouteKey ($request),
         'ip_route' => $this->getIpRouteKey ($request),
         default => $this->getIpKey ($request)
      };
   }

   private function getIpKey (mixed $request): string
   {
      return 'ip:' . ($request->getClientIp () ?? 'unknown');
   }

   private function getUserKey (): string
   {
      $token = $this->tokenStorage->getToken ();
      $userId = $token?->getUser ()?->getUserIdentifier () ?? 'anonymous';

      return 'user:' . $userId;
   }

   private function getIpUserKey (mixed $request): string
   {
      $ip = $request->getClientIp () ?? 'unknown';
      $token = $this->tokenStorage->getToken ();
      $userId = $token?->getUser ()?->getUserIdentifier () ?? 'anonymous';

      return "ip_user:{$ip}:{$userId}";
   }

   private function getRouteKey (mixed $request): string
   {
      $route = $request->attributes->get ('_route', 'unknown');

      return 'route:' . $route;
   }

   private function getIpRouteKey (mixed $request): string
   {
      $ip = $request->getClientIp () ?? 'unknown';
      $route = $request->attributes->get ('_route', 'unknown');

      return "ip_route:{$ip}:{$route}";
   }
}
