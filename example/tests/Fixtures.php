<?php

namespace Tests;

use Wisp\Testing\FixtureFactory;

function createUser (array $overrides = []): array
{
   return array_merge ([
      'email' => 'user@example.com',
      'password' => 'secret'
   ], $overrides);
}

function createValidUserFixture (array $overrides = []): array
{
   static $factory = null;
   $factory ??= new FixtureFactory ();
   return $factory->create (\Example\Request\CreateUserRequest::class, $overrides);
}
