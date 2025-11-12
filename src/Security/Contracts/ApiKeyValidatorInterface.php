<?php

namespace Wisp\Security\Contracts;

use Symfony\Component\Security\Core\User\UserInterface;

interface ApiKeyValidatorInterface
{
   /**
    * Validate an API key and return the associated user
    *
    * @param string $plaintext The plaintext API key to validate
    * @return UserInterface|null Returns user if key is valid, null otherwise
    */
   public function validate (string $plaintext) : ?UserInterface;
}
