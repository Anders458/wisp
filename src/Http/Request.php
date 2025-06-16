<?php

namespace Wisp\Http;

use Stringable;
use Wisp\Container;
use Wisp\Http\CookieJar;
use Wisp\Http\Headers;
use Wisp\Http\Session;
use Wisp\Route;
use Wisp\Router;
use Wisp\Url;
use Wisp\Url\Parse;

class Request
{
   public string $method;
   public Url $url;
   public ?Route $route;
   public array $params;
   public array $query;
   public array $post;

   public Headers $headers;
   public CookieJar $cookies;

   public Response $response;
   public bool $forwarding;
   
   public Context $context;
   public ?Session $session;

   public function __construct (
      ?string $method = null, 
      ?Url $url = null, 
      ?Headers $headers = null, 
      ?CookieJar $cookies = null
   )
   {
      if (!$method) {
         $method = self::getMethod ();
      }

      if (!$url) {
         $url = self::getUrl ();
      }

      if (!$headers) {
         $headers = self::getHeaders ();
      }

      if (!$cookies) {
         $cookies = self::getCookies ();
      }

      $this->method = $method;
      $this->url = $url;
      $this->headers = $headers;
      $this->cookies = $cookies;

      $this->query = $_GET;
      $this->post = $_POST;

      if ($input = $this->input ()) {
         if (is_array ($input)) {
            $this->post = array_merge ($this->post, $input);
         }
      }

      $this->route = null;
      $this->params = [];

      $this->response = new Response ($this);
      $this->forwarding = false;

      $this->context = new Context ();
   }

   public function forward (string $to) : void
   {
      Container::get ()
         ->resolve (Router::class)
         ->forward ($to);
   }

   public static function getCookies () : CookieJar
   {
      $cookies = new CookieJar ();

      foreach ($_COOKIE as $key => $value) {
         $cookies [$key] = $value;
      }

      return $cookies;
   }

   public static function getHeaders () : Headers
   {
      $headers = new Headers ();

      foreach (getallheaders () as $key => $value) {
         $headers [$key] = $value;
      }

      return $headers;
   }

   public static function getMethod () : string
   {
      return $_SERVER ['REQUEST_METHOD'];
   }

   public static function getUrl () : Url
   {
      $url = new Url ();

      if (isset ($_SERVER ['HTTPS']) && $_SERVER ['HTTPS'] === 'on') {
         $url->protocol = 'https';
      } else {
         $url->protocol = 'http';
      }

      if (isset ($_SERVER ['PHP_AUTH_USER'])) {
         $url->username = $_SERVER ['PHP_AUTH_USER'];
      } else {
         $url->username = null;
      }

      if (isset ($_SERVER ['PHP_AUTH_PW'])) {
         $url->password = $_SERVER ['PHP_AUTH_PW'];
      } else {
         $url->password = null;
      }

      $host = Parse::host ($_SERVER ['HTTP_HOST']);

      if ($host ['subdomain']) {
         $url->subdomain = $host ['subdomain'];
      } else {
         $url->subdomain = null;
      }

      if ($host ['domain']) {
         $url->domain = $host ['domain'];
      } else {
         $url->domain = null;
      }

      $url->host = $_SERVER ['HTTP_HOST'];
      $url->port = $_SERVER ['SERVER_PORT'];
      
      $url->query = [];
      
      if (isset ($_SERVER ['QUERY_STRING'])) {
         parse_str ($_SERVER ['QUERY_STRING'], $url->query);

         foreach ($url->query as &$value) {
            if (is_numeric ($value)) {
               $value = (float) $value;
            }
         }

         $path = str_replace ($_SERVER ['QUERY_STRING'], '', $_SERVER ['REQUEST_URI']);
         $path = rtrim ($path, '?');
      } else {
         $path = $_SERVER ['REQUEST_URI'];
      }

      $url->path = $path;

      return $url;
   }

   public static function input (bool $decode = true) : mixed
   {
      $input = file_get_contents ('php://input');

      if ($decode && ($decoded = json_decode ($input, true))) {
         return $decoded; 
      } else {
         return $input ?: null;
      }
   }

   public function ip () : ?string
   {
      return $_SERVER ['REMOTE_ADDR'] ?? null;
   }

   public function origin () : ?string
   {
      if (!empty ($_SERVER ['HTTP_X_FORWARDED_FOR'])) {
         $ips = explode (',', $_SERVER ['HTTP_X_FORWARDED_FOR']);
         $ip  = trim ($ips [0]);

         if (filter_var ($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return $ip;
         }
      }

      return $_SERVER ['HTTP_ORIGIN'] ?? null;
   }

   public function toArray () : array
   {
      return [
         'method'  => $this->method,
         'url'     => $this->url->toArray (),
         'ip'      => $this->ip (),
         'origin'  => $this->origin (),
         'post'    => $this->post,
         'headers' => $this->headers->toArray (),
         'cookies' => $this->cookies->toArray ()
      ];
   }

   // Dynamic properties may be set on the object.
   // This prevents a warning being thrown.
   public function __get (string $key) : mixed
   {
      return null;
   }
}