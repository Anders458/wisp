<?php

namespace Wisp\Security;

use Psr\Cache\CacheItemPoolInterface;
use Wisp\Contracts\TokenProviderInterface;

class CacheTokenProvider implements TokenProviderInterface
{
   public function __construct (
      private CacheItemPoolInterface $cache,
      private array $ttl = [
         'access' => 3600,
         'refresh' => 604800
      ]
   )
   {
   }

   public function become (int | string $userId, array $roles, array $permissions) : array
   {
      $accessToken = bin2hex (random_bytes (32));
      $refreshToken = bin2hex (random_bytes (32));

      $hashedAccessToken = hash ('sha256', $accessToken);
      $hashedRefreshToken = hash ('sha256', $refreshToken);

      $sessionData = [
         'user_id'               => $userId,
         'roles'                 => $roles,
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
         'user_id'               => $userId,
         'roles'                 => $roles,
         'permissions'           => $permissions,
         'hashed_access_token'   => $hashedAccessToken
      ]);

      $refreshItem->expiresAfter ($this->ttl ['refresh']);

      $this->cache->save ($refreshItem);

      $this->addToRegistry ($hashedAccessToken, $sessionData);

      return [
         'access_token'  => $accessToken,
         'token_type'    => 'Bearer',
         'expires_in'    => $this->ttl ['access'],
         'refresh_token' => $refreshToken
      ];
   }

   public function refresh (string $refreshToken) : ?array
   {
      $hashedRefreshToken = hash ('sha256', $refreshToken);
      $refreshItem = $this->cache->getItem ("wisp:refresh:{$hashedRefreshToken}");

      if (!$refreshItem->isHit ()) {
         return null;
      }

      $refreshData = $refreshItem->get ();

      $this->cache->deleteItem ("wisp:access:{$refreshData ['hashed_access_token']}");
      $this->cache->deleteItem ("wisp:refresh:{$hashedRefreshToken}");
      $this->removeFromRegistry ($refreshData ['hashed_access_token']);

      return $this->become (
         $refreshData ['user_id'],
         $refreshData ['roles'],
         $refreshData ['permissions']
      );
   }

   public function revoke (string $token) : bool
   {
      $hashedToken = hash ('sha256', $token);

      $accessItem = $this->cache->getItem ("wisp:access:{$hashedToken}");

      if ($accessItem->isHit ()) {
         $sessionData = $accessItem->get ();
         $this->cache->deleteItem ("wisp:access:{$hashedToken}");
         $this->cache->deleteItem ("wisp:refresh:{$sessionData ['hashed_refresh_token']}");
         $this->removeFromRegistry ($hashedToken);
         return true;
      }

      $refreshItem = $this->cache->getItem ("wisp:refresh:{$hashedToken}");

      if ($refreshItem->isHit ()) {
         $refreshData = $refreshItem->get ();
         $this->cache->deleteItem ("wisp:access:{$refreshData ['hashed_access_token']}");
         $this->cache->deleteItem ("wisp:refresh:{$hashedToken}");
         $this->removeFromRegistry ($refreshData ['hashed_access_token']);
         return true;
      }

      return false;
   }

   public function validate (string $accessToken) : ?array
   {
      $hashedAccessToken = hash ('sha256', $accessToken);
      $accessItem = $this->cache->getItem ("wisp:access:{$hashedAccessToken}");

      if (!$accessItem->isHit ()) {
         return null;
      }

      return $accessItem->get ();
   }

   public function list () : array
   {
      $registryItem = $this->cache->getItem ('wisp:token:registry');

      if (!$registryItem->isHit ()) {
         return [];
      }

      $registry = $registryItem->get () ?? [];
      $tokens = [];

      foreach ($registry as $hashedToken => $metadata) {
         $item = $this->cache->getItem ("wisp:access:{$hashedToken}");

         if ($item->isHit ()) {
            $tokens [] = $item->get ();
         } else {
            $this->removeFromRegistry ($hashedToken);
         }
      }

      return $tokens;
   }

   private function addToRegistry (string $hashedToken, array $data) : void
   {
      $registryItem = $this->cache->getItem ('wisp:token:registry');
      $registry = $registryItem->isHit () ? $registryItem->get () : [];

      $registry [$hashedToken] = [
         'user_id' => $data ['user_id'],
         'created_at' => $data ['created_at']
      ];

      $registryItem->set ($registry);
      $this->cache->save ($registryItem);
   }

   private function removeFromRegistry (string $hashedToken) : void
   {
      $registryItem = $this->cache->getItem ('wisp:token:registry');

      if (!$registryItem->isHit ()) {
         return;
      }

      $registry = $registryItem->get ();
      unset ($registry [$hashedToken]);

      $registryItem->set ($registry);
      $this->cache->save ($registryItem);
   }
}
