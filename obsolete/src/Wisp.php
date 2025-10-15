<?php

namespace Wisp;

use Wisp\Environment\Runtime;
use Wisp\Environment\Stage;
use Wisp\Service\Flash;
use Wisp\Util\Logger;

class Wisp
{
   public static function router () : Router
   {
      return new Router ();
   }

   public static function setup (array $settings)
   {
      $runtime = new Runtime (
         [
            'debug'   => $settings ['debug']   ?? false,
            'version' => $settings ['version'] ?? '1.0.0',
            'stage'   => $settings ['stage']   ?? Stage::production 
         ]
      );

      Container::get ()
         ->bind (Runtime::class, fn () => $runtime)
         ->bind (Logger::class, fn () => new Logger ())
         ->bind (Flash::class, fn () => new Flash ());
   }

   public static function url (array $params)
   {
      $url = new Url ();

      foreach ($params as $key => $value) {
         if (property_exists ($url, $key)) {
            $url->$key = $value;
         }
      }

      return $url;
   }
}