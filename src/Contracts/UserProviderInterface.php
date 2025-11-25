<?php

namespace Wisp\Contracts;

use Symfony\Component\Security\Core\User\UserInterface;

interface UserProviderInterface
{
   public function loadUser (string | int $identifier) : ?UserInterface;
}