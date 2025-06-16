<?php

namespace Wisp;

use Durt\Log;
use SplStack;
use Stringable;
use Wisp\Http\Request;
use Wisp\Url\Parse;
use Wisp\Url\Pattern;

class Constraint implements Stringable
{
   protected ?RouteGroup $parent;

   private array $methods;
   private array $protocols;
   private array $auths;
   private array $ports;
   private array $subdomains;
   private array $domains;
   private array $paths;
   // private array $queries;

   public function __construct (?RouteGroup $parent)
   {
      $this->parent = $parent;

      $this->methods = [];
      $this->protocols = [];
      $this->auths = [];
      $this->ports = [];
      $this->subdomains = [];
      $this->domains = [];
      $this->paths = [];
      // $this->queries = [];
   }

   public function alias (string $path) : self
   {
      return $this->path ($path);
   }

   public function auth (string $username, string $password) : self
   {
      $this->auths [] = [ $username, $password ];
      return $this;
   }

   public function domain (string $domain) : self
   {
      $this->domains [] = $domain;
      return $this;
   }

   /*
      /v1
         /path-1
         /path-2
            /path-3
            /path-4
               /path-5

      /v1/path-1/path-3/path-5
      /v1/path-1/path-4/path-5
      /v1/path-2/path-3/path-5
      /v1/path-2/path-4/path-5
   */
   public function getFullPaths () : array
   {
      $chain = new SplStack ();
      $chain [] = $this;

      if (isset ($this->parent)) {
         $parent = $this->parent;

         while ($parent) {
            $chain [] = $parent;
            $parent = $parent->parent;
         }
      }

      $fullPaths = [];

      foreach ($chain as $constraint) {
         $paths = $constraint->getPaths ();

         if (empty ($paths)) {
            continue;
         }

         if (empty ($fullPaths)) {
            $fullPaths = $paths;
         } else {
            $newFullPaths = [];

            foreach ($fullPaths as $fullPath) {
               foreach ($paths as $path) {
                  $newFullPaths [] = $fullPath . $path;
               }
            }

            $fullPaths = $newFullPaths;
         }
      }

      return $fullPaths;
   }

   public function getPaths () : array
   {
      return $this->paths;
   }

   public function host (string $host) : self
   {
      $parsed = Parse::host ($host);

      if ($parsed ['subdomain']) {
         $this->subdomain ($parsed ['subdomain']);
      }

      if ($parsed ['domain']) {
         $this->domain ($parsed ['domain']);
      }

      if ($parsed ['port']) {
         $this->port ($parsed ['port']);
      }

      return $this;
   }

   public function matches (Request $request) : array | false
   {
      $method = $request->method;
      $url = $request->url;

      if (!empty ($this->methods) && !in_array ($method, $this->methods)) {
         return false;
      }

      if (isset ($url->protocol) && !empty ($this->protocols) && !in_array ($url->protocol, $this->protocols)) {
         return false;
      }

      if (isset ($url->username) && isset ($url->password) && !empty ($this->auths)) {
         $found = false;

         foreach ($this->auths as $auth) {
            if ($auth [0] === $url->username && $auth [1] === $url->password) {
               $found = true;
               break;
            }
         }

         if (!$found) {
            return false;
         }
      }

      if (isset ($url->subdomain) && !empty ($this->subdomains) && !in_array ($url->subdomain, $this->subdomains)) {
         return false;
      }

      if (isset ($url->domain) && !empty ($this->domains) && !in_array ($url->domain, $this->domains)) {
         return false;
      }

      if (isset ($url->port) && !empty ($this->ports) && !in_array ($url->port, $this->ports)) {
         return false;
      }

      // if (isset ($url->query) && !empty ($this->queries)) {
      //    $found = false;

      //    foreach ($this->queries as $pairs) {
      //       $all = true;

      //       foreach ($pairs as $pair) {
      //          if (is_array ($pair)) {
      //             if (!isset ($url->query [$pair [0]]) || (isset ($url->query [$pair [0]]) && $url->query [$pair [0]] !== $pair [1])) {
      //                $all = false;
      //             }
      //          } else if (is_string ($pair)) {
      //             if (!isset ($url->query [$pair])) {
      //                $all = false;
      //                break;
      //             }
      //          }
      //       }

      //       if ($all) {
      //          $found = true;
      //          break;
      //       }
      //    }

      //    if (!$found) {
      //       return false;
      //    }
      // }

      if (isset ($url->path) && !empty ($this->paths)) {
         $found = false;

         foreach ($this->getFullPaths () as $path) {
            $pattern = new Pattern ($path);

            if ($this instanceof RouteGroup) {
               if ($pattern->isPrefixOf ($url->path)) {
                  $found = true;
                  break;
               }
            } else if ($this instanceof Route) {
               if ($pattern->matches ($url->path)) {
                  return $pattern->getMatchedGroups ();
               }
            }
         }

         if (!$found) {
            return false;
         }
      }

      return [];
   }

   public function method (string $method) : self
   {
      $this->methods [] = strtoupper ($method);
      return $this;
   }

   public function path (string $path) : self
   {
      // if ($path) {
         $this->paths [] = $path;
      // }

      return $this;
   }

   public function port (int $port) : self
   {
      $this->ports [] = $port;
      return $this;
   }

   public function protocol (string $protocol) : self
   {
      $this->protocols [] = $protocol;
      return $this;
   }

   // public function query (... $pairs) : self
   // {
   //    $this->queries [] = $pairs;
   //    return $this;
   // }

   public function secure (bool $secure) : self
   {
      if ($secure) {
         $this->protocol ('https');
      } else {
         $this->protocol ('http');
      }

      return $this;
   }

   public function subdomain (string $subdomain) : self
   {
      $this->subdomains [] = $subdomain;
      return $this;
   }

   public function toString () : string
   {
      $form = [];

      foreach ([
         'methods',
         'protocols',
         'auths',
         'ports',
         'subdomains',
         'domains'
      ] as $constraint) {
         if (!empty ($this->$constraint)) {
            $form [] = ucfirst ($constraint) . ' = ' . join (' | ', $this->$constraint);
         }
      }

      if (!empty ($fullPaths = $this->getFullPaths ())) {
         $form [] = 'Paths: ' . join (' | ', $fullPaths);
      }

      if (empty ($form)) {
         return 'None';
      }
      
      return implode (', ', $form);
   }

   public function __toString () : string
   {
      return $this->toString ();
   }
}