<?php

namespace Wisp\Security;

interface BearerDecoderInterface
{
   /**
    * Decode a bearer token and return its claims.
    *
    * @param string $token The raw bearer token
    * @return array<string, mixed>|null Claims array if valid, null if invalid/expired
    */
   public function decode (string $token): ?array;
}
