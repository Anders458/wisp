<?php

namespace Wisp\Middleware;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Wisp\Http\Request;
use Wisp\Http\Response;

class Authentication
{
   public function __construct (
      private CacheItemPoolInterface $cache,
      private SessionInterface $session,
      private Request $request,
      private Response $response,
      private array $ttl = [ 
         'access' => 3600, 
         'refresh' => 604800 
      ]
   )
   {
   }

   public function before ()
   {
      if (!$this->request->headers->has ('Authorization')) {
         return;
      }

      $header = $this->request->headers->get ('Authorization', '');

      if (!str_starts_with ($header, 'Bearer ')) {
         return $this->response
            ->status (401)
            ->error ('Token-based authentication requires a Bearer token set in the Authorization header');
      }

      $accessToken = substr ($header, 7);

      $hashedAccessToken = sha256 ($accessToken);
      $accessItem = $this->cache->getItem ("wisp:access:{$hashedAccessToken}");

      if (!$accessItem->isHit ()) {
         return $this->response
            ->status (401)
            ->error ('Invalid or expired access token');
      }

      $sessionData = $accessItem->get ();

      $this->session->setId ($hashedAccessToken);

      if (!$this->session->isStarted ()) {
         $this->session->start ();
      }

      $sessionKeys = [
         'user_id',
         'role',
         'permissions',
         'created_at'
      ];

      foreach ($sessionKeys as $key) {
         if (isset ($sessionData [$key])) {
            $this->session->set ($key, $sessionData [$key]);
         }
      }
   }

   public function after () : void
   {
      if ($this->session->isStarted ()) {
         $this->session->save ();
      }
   }

   public function become (int | string $userId, string $role, array $permissions) : array
   {
      $accessToken = bin2hex (random_bytes (32));
      $refreshToken = bin2hex (random_bytes (32));

      $hashedAccessToken = sha256 ($accessToken);
      $hashedRefreshToken = sha256 ($refreshToken);

      $sessionData = [
         'user_id'               => $userId,
         'role'                  => $role,
         'permissions'           => $permissions,
         'created_at'            => time (),
         'hashed_refresh_token'  => $hashedRefreshToken
      ];

      $accessItem = $this->cache->getItem ("wisp:access:{$hashedAccessToken}");
      $accessItem->set ($sessionData);
      $accessItem->expiresAfter ($this->ttl ['access']);

      $this->cache->save ($accessItem);

      $refreshItem = $this->cache->getItem ("wisp:refresh:{$hashedRefreshToken}");
      $refreshItem->set ([
         'user_id' => $userId,
         'role' => $role,
         'permissions' => $permissions,
         'hashed_access_token' => $hashedAccessToken
      ]);

      $refreshItem->expiresAfter ($this->ttl ['refresh']);
      
      $this->cache->save ($refreshItem);

      return [
         'access_token'  => $accessToken,
         'token_type'    => 'Bearer',
         'expires_in'    => $this->ttl ['access'],
         'refresh_token' => $refreshToken
      ];
   }

   public function refresh (string $refreshToken) : ?array
   {
      $hashedRefreshToken = sha256 ($refreshToken);
      $refreshItem = $this->cache->getItem ("wisp:refresh:{$hashedRefreshToken}");

      if (!$refreshItem->isHit ()) {
         return null;
      }

      $refreshData = $refreshItem->get ();

      $this->cache->deleteItem ("wisp:access:{$refreshData ['hashed_access_token']}");
      $this->cache->deleteItem ("wisp:refresh:{$hashedRefreshToken}");

      return $this->become (
         $refreshData ['user_id'],
         $refreshData ['role'],
         $refreshData ['permissions']
      );
   }

   public function revoke (string $refreshToken) : bool
   {
      $refreshHash = sha256 ($refreshToken);
      $refreshItem = $this->cache->getItem ("wisp:refresh:{$refreshHash}");

      if (!$refreshItem->isHit ()) {
         return false;
      }

      $refreshData = $refreshItem->get ();

      $this->cache->deleteItem ("wisp:access:{$refreshData ['hashed_access_token']}");
      $this->cache->deleteItem ("wisp:refresh:{$refreshHash}");

      return true;
   }
}
