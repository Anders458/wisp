<?php

namespace App\Testing;

use Wisp\Testing\HasTestScenarios;

/**
 * Test scenarios for GET /v1/users/@me
 *
 * Note: Session-based auth testing requires PHPUnit as the console test
 * command doesn't persist sessions between requests. Use UserTest for
 * complete session testing.
 */
class UserMeScenarios implements HasTestScenarios
{
   public static function testScenarios (): array
   {
      return [
         'unauthenticated' => [
            'request' => [],
            'expect' => 'auth_error'
         ],
         'with_token' => [
            'setup' => [
               [
                  'method' => 'POST',
                  'path' => '/v1/auth/token',
                  'body' => [
                     'email' => 'user@example.com',
                     'password' => 'password123'
                  ],
                  'extract' => [
                     'token' => 'body.token'
                  ]
               ]
            ],
            'request' => [
               'headers' => [
                  'Authorization' => 'Bearer {{token}}'
               ]
            ],
            'expect' => 'success'
         ],
         'with_admin_token' => [
            'setup' => [
               [
                  'method' => 'POST',
                  'path' => '/v1/auth/token',
                  'body' => [
                     'email' => 'admin@example.com',
                     'password' => 'admin123'
                  ],
                  'extract' => [
                     'token' => 'body.token'
                  ]
               ]
            ],
            'request' => [
               'headers' => [
                  'Authorization' => 'Bearer {{token}}'
               ]
            ],
            'expect' => 'success'
         ],
         'with_invalid_token' => [
            'request' => [
               'headers' => [
                  'Authorization' => 'Bearer invalid-token'
               ]
            ],
            'expect' => 'auth_error'
         ]
      ];
   }
}
