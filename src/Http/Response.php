<?php

namespace Wisp\Http;

use Wisp\Container;
use Wisp\Environment\Runtime;
use Wisp\Http;
use Wisp\Http\CookieJar;
use Wisp\Http\Headers;
use Wisp\Service\Flash;
use Wisp\Url;

class Response
{
   public Request $request;

   public int    $code;
   public string $status;
   public mixed  $body;
   public bool   $sent;

   public Headers   $headers;
   public CookieJar $cookies;

   public Context $context;

   public function __construct (Request $request)
   {
      $this->request = $request;
      
      $this->code = 200;
      $this->status = "OK";
      $this->body = null;
      $this->sent = false;

      $this->headers = new Headers ();
      $this->cookies = new CookieJar ();

      $this->context = new Context ();
   }

   public function body (mixed $body) : self
   {
      $this->body = $body;
      return $this;
   }

   public function error (string $message, ?int $code = null) : self
   {
      Container::get ()->resolve (Flash::class)->error ($message, $code);
      return $this;
   }

   public function warning (string $message, ?int $code = null) : self
   {
      Container::get ()->resolve (Flash::class)->warning ($message, $code);
      return $this;
   }

   public function redirect (string | Url $url, int $code = 301)
   {
      $this->status ($code);
      $this->headers ['Location'] = (string) $url;
      $this->send ();
   }

   public function send ()
   {
      if (!headers_sent ()) {
         http_response_code ($this->code);

         foreach ($this->headers as $name => $value) {
            header ("$name: $value");
         }

         foreach ($this->cookies as $cookie) {
            setcookie (
               $cookie->name,
               $cookie->value,
               [
                  'expires' => $cookie->expires,
                  'path' => $cookie->path,
                  'domain' => $cookie->domain,
                  'secure' => $cookie->secure,
                  'httponly' => $cookie->httponly,
                  'samesite' => $cookie->samesite
               ]
            );
         }
      }

      if ($this->headers->has ('Content-Type', 'application/json')) {
         print (json_encode ($this->body, Container::get ()->resolve (Runtime::class)->isDebug () ? JSON_PRETTY_PRINT : 0));
      } else {
         print ($this->body);
      }
      
      $this->sent = true;
   }

   public function status (string | int | null $codeOrStatus = null) : self
   {
      if (is_string ($codeOrStatus)) {
         $this->code = Http::code ($codeOrStatus);
         $this->status = $codeOrStatus;
      } else if (is_int ($codeOrStatus)) {
         $this->code = $codeOrStatus;
         $this->status = Http::status ($codeOrStatus);
      }

      return $this;
   }

   public function toArray () : array
   {
      return [
         'code'    => $this->code,
         'status'  => $this->status,
         'headers' => $this->headers->toArray (),
         'cookies' => $this->cookies->toArray ()
      ];
   }
}