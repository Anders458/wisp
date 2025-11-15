<?php

namespace Wisp\Security\Contracts;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * KeyValidatorInterface
 *
 * Validates API keys and returns the associated user.
 */
interface KeyValidatorInterface
{
   /**
    * Validate an API key and return the associated user.
    *
    * @param string $key The API key to validate
    * @return UserInterface|null The user if valid, null otherwise
    */
   public function validate (string $key) : ?UserInterface;
}
