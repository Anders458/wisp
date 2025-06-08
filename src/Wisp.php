<?php

namespace Wisp;

use Wisp\Environment\Runtime;
use Wisp\Environment\Stage;
use Wisp\Middleware\CORS;
use Wisp\Util\Config;
use Wisp\Util\Logger;

class Wisp
{
   private static Config $config;
   private static Container $container;

   public static function config () : Config
   {
      return self::$config;
   }

   public static function container () : Container
   {
      return self::$container;
   }

   public static function resolve (string $id) : mixed
   {
      return self::$container->resolve ($id);
   }
   
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
         ->bind (Runtime::class, fn () => $runtime);

      // self::$config = new Config ();
      
      // if (isset ($settings ['container']) && $settings ['container'] instanceof Container) {
      //    self::$container = $settings ['container'];
      // } else {
      //    self::$container = new Container ();
      // }

      // self::$config [] = [
      //    'logger' => new Logger (),

      //    'cookies' => [
      //       'lifetime' => 0,
      //       'expires' => 0,
      //       'path' => '',
      //       'domain' => '',
      //       'secure' => false,
      //       'httponly' => false,
      //       'samesite' => 'Lax',
      //    ],
   
      //    'debug' => false,
      //    'defaults' => [],
      //    'guards' => [],
      //    'services' => []
      // ];
   
      // self::$config [] = $settings;

      // session_set_cookie_params (
      //    [
      //       'lifetime' => self::$config ['cookies.lifetime'],
      //       'path' => self::$config ['cookies.path'],
      //       'domain' => self::$config ['cookies.domain'],
      //       'secure' => self::$config ['cookies.secure'],
      //       'httponly' => self::$config ['cookies.httponly'],
      //       'samesite' => self::$config ['cookies.samesite']
      //    ]
      // );
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