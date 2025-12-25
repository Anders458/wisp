<?php

namespace App\Security;

use Wisp\Security\BearerDecoderInterface;

/**
 * Test bearer decoder that accepts specific test tokens.
 */
class TestBearerDecoder implements BearerDecoderInterface
{
   private array $validTokens = [
      'valid-token' => [
         'sub' => 'user-123',
         'scope' => 'user',
         'iat' => 1700000000,
         'exp' => 9999999999
      ],
      'admin-token' => [
         'sub' => 'admin-456',
         'scope' => 'admin',
         'iat' => 1700000000,
         'exp' => 9999999999
      ],
      'expired-token' => [
         'sub' => 'user-789',
         'scope' => 'user',
         'iat' => 1600000000,
         'exp' => 1600000001
      ],
      'premium-token' => [
         'sub' => 'premium-user',
         'scope' => 'premium',
         'iat' => 1700000000,
         'exp' => 9999999999
      ]
   ];

   public function decode (string $token): ?array
   {
      // Check for expired token
      if ($token === 'expired-token') {
         return null;
      }

      return $this->validTokens [$token] ?? null;
   }
}
