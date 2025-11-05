<?php

/**
 * Authorization Guard Middleware
 *
 * Enforces role and permission requirements set via ->is() and ->can()
 * on routes and route groups.
 *
 * USAGE:
 *
 * This middleware is automatically registered by the framework and runs
 * after Authentication middleware to enforce authorization rules.
 *
 * 1. Role-based access (->is):
 *
 *    $app->get ('/admin', [ AdminController::class, 'index' ])
 *       ->is ('admin');
 *
 *    // Multiple roles (OR logic - user must have at least one)
 *    $app->get ('/dashboard', [ DashboardController::class, 'index' ])
 *       ->is ([ 'admin', 'moderator' ]);
 *
 * 2. Permission-based access (->can):
 *
 *    $app->post ('/posts', [ PostController::class, 'create' ])
 *       ->can ('create:posts');
 *
 *    // Multiple permissions (AND logic - user must have all)
 *    $app->post ('/publish', [ PostController::class, 'publish' ])
 *       ->can ([ 'edit:posts', 'publish:posts' ]);
 *
 * 3. Group-level guards (inherited by all routes):
 *
 *    $app->group ('/admin', fn ($g) =>
 *       $g
 *          ->is ('admin')
 *          ->get ('/users', [ AdminController::class, 'users' ])
 *          ->delete ('/users/{id}', [ AdminController::class, 'delete' ])
 *             ->can ('delete:users')  // Additional permission on route
 *    );
 *
 * 4. Combined role + permission:
 *
 *    $app->post ('/sensitive', [ SensitiveController::class, 'action' ])
 *       ->is ('admin')
 *       ->can ('dangerous:action');
 *
 * BEHAVIOR:
 *
 * - Unauthenticated users: 401 if guards are present, allowed if no guards
 * - Wrong role: 403 Forbidden
 * - Missing permission: 403 Forbidden
 * - Role check: User must have ONE of the required roles (OR logic)
 * - Permission check: User must have ALL required permissions (AND logic)
 */

namespace Wisp\Middleware;

use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Wisp\Http\Request;
use Wisp\Http\Response;
use Wisp\Router;

class Guard
{
   public function __construct (
      private Router $router,
      private SessionInterface $session,
      private Request $request,
      private Response $response
   )
   {
   }

   public function before ()
   {
      $routeName = $this->request->attributes->get ('_route');

      if (!$routeName) {
         return;
      }

      $route = $this->router->find ($routeName);

      if (!$route) {
         return;
      }

      // Check role requirements
      if (!empty ($route->roles)) {
         if (!$this->checkRoles ($route->roles)) {
            return $this->response
               ->status (403)
               ->error ('Forbidden: Insufficient role');
         }
      }

      // Check permission requirements
      if (!empty ($route->permissions)) {
         if (!$this->checkPermissions ($route->permissions)) {
            return $this->response
               ->status (403)
               ->error ('Forbidden: Insufficient permissions');
         }
      }
   }

   private function checkPermissions (array $required) : bool
   {
      if (!$this->session->has ('user_id')) {
         return false;
      }

      $userPermissions = $this->session->get ('permissions', []);

      // User must have ALL required permissions (AND logic)
      foreach ($required as $permission) {
         if (!in_array ($permission, $userPermissions)) {
            return false;
         }
      }

      return true;
   }

   private function checkRoles (array $required) : bool
   {
      if (!$this->session->has ('user_id')) {
         return false;
      }

      $userRole = $this->session->get ('role');

      // User must have ONE of the required roles (OR logic)
      return in_array ($userRole, $required);
   }
}
