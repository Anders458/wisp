<?php

namespace Wisp\Security\Contracts;

use Symfony\Component\Security\Core\User\UserInterface;

interface OAuthUserMapperInterface
{
   /**
    * Map an OAuth user to a local user
    *
    * This method should find or create a local user based on the OAuth user data
    *
    * @param object $oauthUser The OAuth user object from the provider
    * @param string $provider The OAuth provider name (e.g., 'github', 'google')
    * @return UserInterface|null Returns user if mapping successful, null otherwise
    */
   public function map (object $oauthUser, string $provider) : ?UserInterface;
}
