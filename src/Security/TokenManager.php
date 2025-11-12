<?php

namespace Wisp\Security;

use Psr\Cache\CacheItemPoolInterface;

class TokenManager
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

   /**
    * Generate access and refresh tokens for a user
    *
    * @param int|string $userId User identifier
    * @param string $role User role
    * @param array $permissions User permissions
    * @return array Token data including access_token, refresh_token, token_type, expires_in
    */
   public function become (int | string $userId, string $role, array $permissions) : array
   {
      $accessToken = bin2hex (random_bytes (32));
      $refreshToken = bin2hex (random_bytes (32));

      $hashedAccessToken = hash ('sha256', $accessToken);
      $hashedRefreshToken = hash ('sha256', $refreshToken);

      $sessionData = [
         'user_id'               => $userId,
         'role'                  => $role,
         'permissions'           => $permissions,
         'created_at'            => time (),
         'hashed_refresh_token'  => $hashedRefreshToken
      ];

      // Store access token
      $accessItem = $this->cache->getItem ("wisp:access:{$hashedAccessToken}");
      $accessItem->set ($sessionData);
      $accessItem->expiresAfter ($this->ttl ['access']);
      $this->cache->save ($accessItem);

      // Store refresh token
      $refreshItem = $this->cache->getItem ("wisp:refresh:{$hashedRefreshToken}");
      $refreshItem->set ([
         'user_id'               => $userId,
         'role'                  => $role,
         'permissions'           => $permissions,
         'hashed_access_token'   => $hashedAccessToken
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

   /**
    * Get token TTL settings
    *
    * @return array
    */
   public function getTtl () : array
   {
      return $this->ttl;
   }

   /**
    * Refresh an access token using a refresh token
    *
    * @param string $refreshToken The refresh token
    * @return array|null New token data or null if refresh token is invalid
    */
   public function refresh (string $refreshToken) : ?array
   {
      $hashedRefreshToken = hash ('sha256', $refreshToken);
      $refreshItem = $this->cache->getItem ("wisp:refresh:{$hashedRefreshToken}");

      if (!$refreshItem->isHit ()) {
         return null;
      }

      $refreshData = $refreshItem->get ();

      // Delete old tokens
      $this->cache->deleteItem ("wisp:access:{$refreshData ['hashed_access_token']}");
      $this->cache->deleteItem ("wisp:refresh:{$hashedRefreshToken}");

      // Generate new tokens
      return $this->become (
         $refreshData ['user_id'],
         $refreshData ['role'],
         $refreshData ['permissions']
      );
   }

   /**
    * Revoke tokens using a refresh token
    *
    * @param string $refreshToken The refresh token to revoke
    * @return bool True if tokens were revoked, false if refresh token not found
    */
   public function revoke (string $refreshToken) : bool
   {
      $refreshHash = hash ('sha256', $refreshToken);
      $refreshItem = $this->cache->getItem ("wisp:refresh:{$refreshHash}");

      if (!$refreshItem->isHit ()) {
         return false;
      }

      $refreshData = $refreshItem->get ();

      $this->cache->deleteItem ("wisp:access:{$refreshData ['hashed_access_token']}");
      $this->cache->deleteItem ("wisp:refresh:{$refreshHash}");

      return true;
   }

   /**
    * Validate an access token and return session data
    *
    * @param string $accessToken The plaintext access token
    * @return array|null Session data or null if token is invalid
    */
   public function validateAccessToken (string $accessToken) : ?array
   {
      $hashedAccessToken = hash ('sha256', $accessToken);
      $accessItem = $this->cache->getItem ("wisp:access:{$hashedAccessToken}");

      if (!$accessItem->isHit ()) {
         return null;
      }

      return $accessItem->get ();
   }
}
