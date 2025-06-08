<?php

namespace Wisp;

class Url
{
   public const ALL       = ~0;
   public const PROTOCOL  = 1 << 0;
   public const AUTH      = 1 << 1;
   public const SUBDOMAIN = 1 << 2;
   public const DOMAIN    = 1 << 3;
   public const HOST      = 1 << 4;
   public const PORT      = 1 << 5;
   public const PATH      = 1 << 6;
   public const QUERY     = 1 << 7;
   public const FRAGMENT  = 1 << 8;

   public ?string $protocol;
   public ?string $username;
   public ?string $password;
   public ?string $subdomain;
   public ?string $domain;
   public ?string $host;  
   public ?int $port;
   public ?string $path;
   public ?array $query;
   public ?string $fragment;

   public function toString (int $flags = self::ALL, array $exclude = []) : string
   {
      $url = '';

      if ($flags & self::PROTOCOL) {
         if (isset ($this->protocol)) {
            $url .= "{$this->protocol}://";
         }
      }

      if ($flags & self::AUTH) {
         if (isset ($this->username) && 
             isset ($this->password)) {
            $url .= "{$this->username}:{$this->password}@";
         }
      }

      if ($flags & self::HOST) {
         if (isset ($this->host)) {
            $url .= $this->host;
         }
      } else {
         if ($flags & self::SUBDOMAIN) {
            if (isset ($this->subdomain)) {
               $url .= $this->subdomain . '.';
            }
         }

         if ($flags & self::DOMAIN) {
            if (isset ($this->domain)) {
               $url .= $this->domain;
            }
         }
      }

      if ($flags & self::PORT) {
         if (isset ($this->port)) {
            $url .= ':' . $this->port;
         }
      }

      if ($flags & self::PATH) {
         if (isset ($this->path)) {
            $url .= '/' . ltrim ($this->path, '/');
         }
      }

      if ($flags & self::QUERY) {
         if (isset ($this->query)) {
            $query = array_diff_key ($this->query, array_flip ($exclude));

            if (!empty ($query)) {
               $url .= (!empty ($url) ? '?' : '') . http_build_query ($query);
            }
         }
      }

      if ($flags & self::FRAGMENT) {
         if (isset ($this->fragment)) {
            $url .= '#' . $this->fragment;
         }
      }

      return $url;
   }

   public function __toString () : string
   {
      return $this->toString (self::ALL ^ self::AUTH ^ self::PORT);
   }
}