<?php

namespace App\Shape;

use Symfony\Component\Validator\Constraints as Assert;
use Wisp\Testing\HasTestFixtures;

class LoginRequest implements HasTestFixtures
{
   #[Assert\NotBlank]
   #[Assert\Email]
   public string $email;

   #[Assert\NotBlank]
   public string $password;

   public bool $remember = false;

   public static function testFixtures (): array
   {
      return [
         'valid' => [
            'data' => [
               'email' => 'user@example.com',
               'password' => 'password123'
            ],
            'expect' => 'success'
         ],
         'invalid_credentials' => [
            'data' => [
               'email' => 'user@example.com',
               'password' => 'wrongpassword'
            ],
            'expect' => 'auth_error'
         ],
         'invalid_email_format' => [
            'data' => [
               'email' => 'not-an-email',
               'password' => 'password123'
            ],
            'expect' => 'validation_error'
         ],
         'empty_email' => [
            'data' => [
               'email' => '',
               'password' => 'password123'
            ],
            'expect' => 'validation_error'
         ],
         'empty_password' => [
            'data' => [
               'email' => 'user@example.com',
               'password' => ''
            ],
            'expect' => 'validation_error'
         ]
      ];
   }
}
