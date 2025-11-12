<?php

namespace Example\Security;

use Symfony\Component\Security\Core\User\UserInterface;
use Wisp\Security\Contracts\OAuthUserMapperInterface;
use Wisp\Security\User;

/**
 * Example OAuth user mapper
 *
 * Maps OAuth provider user data to your application's User object.
 * In a real application, you would typically:
 * 1. Check if user exists in database by OAuth provider ID
 * 2. Create new user if not exists
 * 3. Update user profile from OAuth data
 * 4. Return User object with appropriate roles/permissions
 */
class OAuthUserMapper implements OAuthUserMapperInterface
{
   /**
    * Map OAuth user data to application User
    *
    * @param object $oauthUser OAuth user data from provider
    * @param string $provider Provider name (github, google, etc.)
    * @return UserInterface|null
    */
   public function map (object $oauthUser, string $provider) : ?UserInterface
   {
      // In a real app, you would:
      // $stmt = $pdo->prepare ('SELECT * FROM users WHERE oauth_provider = ? AND oauth_id = ?');
      // $stmt->execute ([$provider, $oauthUser->getId ()]);
      // $user = $stmt->fetch ();
      //
      // if (!$user) {
      //    // Create new user
      //    $stmt = $pdo->prepare ('INSERT INTO users (oauth_provider, oauth_id, email, name) VALUES (?, ?, ?, ?)');
      //    $stmt->execute ([$provider, $oauthUser->getId (), $oauthUser->getEmail (), $oauthUser->getName ()]);
      //    $userId = $pdo->lastInsertId ();
      // } else {
      //    $userId = $user ['id'];
      // }

      // For this example, we'll just create a mock user
      return new User (
         id: $oauthUser->getId (),
         role: 'user',
         permissions: ['read:own', 'write:own']
      );
   }
}
