<?php

namespace Wisp\Listener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface as CurrentUserStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Wisp\Http\Response;
use Wisp\Router;

/**
 * AuthorizationListener
 *
 * Automatically enforces role and permission requirements defined on routes
 * via ->is() and ->can() methods. Runs before controller execution.
 *
 * This replaces the need for manual Guard middleware registration.
 *
 * USAGE:
 *
 * Routes with authorization guards are automatically protected:
 *
 *    $app->get ('/admin', [AdminController::class, 'index'])
 *       ->is ('admin');  // Automatically enforced!
 *
 *    $app->post ('/posts', [PostController::class, 'create'])
 *       ->can ('create:posts');  // Automatically enforced!
 */
class AuthorizationListener implements EventSubscriberInterface
{
   public function __construct (
      private Router $router,
      private AuthorizationCheckerInterface $authorizationChecker,
      private CurrentUserStorageInterface $tokenStorage
   )
   {
   }

   public static function getSubscribedEvents () : array
   {
      return [
         // Priority: After RouterListener (32) but before controller (0)
         KernelEvents::REQUEST => [ 'onKernelRequest', 16 ]
      ];
   }

   public function onKernelRequest (RequestEvent $event) : void
   {
      if (!$event->isMainRequest ()) {
         return;
      }

      $request = $event->getRequest ();
      $routeName = $request->attributes->get ('_route');

      if (!$routeName) {
         return;
      }

      $route = $this->router->find ($routeName);

      if (!$route) {
         return;
      }

      // No guards defined - allow access
      if (empty ($route->roles) && empty ($route->permissions)) {
         return;
      }

      $isAuthenticated = $this->tokenStorage->getToken () !== null
         && $this->tokenStorage->getToken ()->getUser () !== null;

      // Check role requirements
      if (!empty ($route->roles)) {
         if (!$isAuthenticated) {
            $event->setResponse (
               (new Response ())
                  ->status (401)
                  ->error ('Authentication required')
            );

            return;
         }

         // User must have ONE of the required roles (OR logic)
         $hasRole = false;
         
         foreach ($route->roles as $role) {
            if ($this->authorizationChecker->isGranted (strtoupper ($role))) {
               $hasRole = true;
               break;
            }
         }

         if (!$hasRole) {
            $event->setResponse (
               (new Response ())
                  ->status (403)
                  ->error ('Forbidden: Insufficient role')
            );
            return;
         }
      }

      // Check permission requirements
      if (!empty ($route->permissions)) {
         if (!$isAuthenticated) {
            $event->setResponse (
               (new Response ())
                  ->status (401)
                  ->error ('Authentication required')
            );
            return;
         }

         // User must have ALL required permissions (AND logic)
         foreach ($route->permissions as $permission) {
            if (!$this->authorizationChecker->isGranted ($permission)) {
               $event->setResponse (
                  (new Response ())
                     ->status (403)
                     ->error ('Forbidden: Insufficient permissions')
               );
               return;
            }
         }
      }
   }
}
