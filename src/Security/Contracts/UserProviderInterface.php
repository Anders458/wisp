<?php

namespace Wisp\Security\Contracts;

use Symfony\Component\Security\Core\User\UserInterface;

interface UserProviderInterface
{
   /**
    * Load user by their unique identifier
    *
    * @param string|int $identifier User ID or unique identifier
    * @return UserInterface|null Returns user if found, null otherwise
    */
   public function loadUser (string | int $identifier) : ?UserInterface;
}
