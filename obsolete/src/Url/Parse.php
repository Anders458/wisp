<?php

namespace Wisp\Url;

use Wisp\Url;

class Parse
{
   public static function host (string $host) : array
   {
      $port = null;

      if (str_contains ($host, ':')) {
         [ $host, $port ] = explode (':', $host);
         $port = (int) $port;
      }
      
      $parts = explode ('.', $host);
      $domain = implode ('.', array_slice ($parts, -2));
      
      if (count ($parts) > 2) {
         $subdomain = implode ('.', array_slice ($parts, 0, -2));
      } else {
         $subdomain = null;
      }

      return [
         'subdomain' => $subdomain,
         'domain' => $domain,
         'port' => $port
      ];
   }

   public static function url (string | Url $qualified) : Url
   {
      if ($qualified instanceof Url) {
         return $qualified;
      }
      
      $url = new Url ();
      $parsed = parse_url ($qualified);

      if (isset ($parsed ['scheme'])) {
         $url->protocol = $parsed ['scheme'];
      }

      if (isset ($parsed ['user'])) {
         $url->username = $parsed ['user'];
      }

      if (isset ($parsed ['pass'])) {
         $url->password = $parsed ['pass'];
      }

      if (isset ($parsed ['host'])) {
         $parsedHost = self::host ($parsed ['host']);

         if ($parsedHost ['subdomain']) {
            $url->subdomain = $parsedHost ['subdomain'];
         }

         if ($parsedHost ['domain']) {
            $url->domain = $parsedHost ['domain'];
         }

         $url->host = $parsed ['host'];
      }

      if (isset ($parsed ['port'])) {
         $url->port = $parsed ['port'];
      }

      if (isset ($parsed ['path'])) {
         $url->path = $parsed ['path'];
      }

      if (isset ($parsed ['query'])) {
         parse_str ($parsed ['query'], $url->query);
      }

      if (isset ($parsed ['fragment'])) {
         $url->fragment = $parsed ['fragment'];
      }

      return $url;
   }
}