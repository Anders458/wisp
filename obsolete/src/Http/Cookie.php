<?php

namespace Wisp\Http;

use Wisp\Wisp;

class Cookie
{
   public string $name;
   public string $value;
   public int $expires;
   public ?string $path;
   public ?string $domain;
   public bool $secure;
   public bool $httponly;
   public string $samesite;

   public function __construct ()
   {
      $defaults = [
         'lifetime' => 3600,
         'path'     => null,
         'domain'   => null,
         'secure'   => true,
         'httponly' => true,
         'samesite' => 'None'
      ];

      if (isset ($defaults ['lifetime'])) {
         $this->expires = time () + $defaults ['lifetime'];
      } else {
         $this->expires = $defaults ['expires'];
      }

      $this->path = $defaults ['path'];
      $this->domain = $defaults ['domain'];
      $this->secure = $defaults ['secure'];
      $this->httponly = $defaults ['httponly'];
      $this->samesite = $defaults ['samesite'];
   }
}