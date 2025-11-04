<?php

namespace Wisp\Http;

use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Wisp\Service\Flash;

class Response extends SymfonyResponse
{
   public function body (string $content) : self
   {
      $this->setContent ($content);
      return $this;
   }

   public function download (string $path, ?string $name = null) : self
   {
      if (!file_exists ($path) || !is_readable ($path)) {
         throw new \RuntimeException ("File not found or not readable: {$path}");
      }

      $name = $name ?? basename ($path);
      $mimeType = mime_content_type ($path) ?: 'application/octet-stream';

      $this->headers->set ('Content-Type', $mimeType);
      $this->headers->set ('Content-Disposition', "attachment; filename=\"{$name}\"");
      $this->headers->set ('Content-Length', (string) filesize ($path));
      $this->setContent (file_get_contents ($path));

      return $this;
   }

   public function error (string $message, ?int $code = null) : self
   {
      container (Flash::class)->error ($message, $code);
      return $this;
   }

   public function getStatus () : string
   {
      return self::$statusTexts [$this->getStatusCode ()] ?? 'Unknown';
   }

   public function html (string $content) : self
   {
      $this->headers->set ('Content-Type', 'text/html; charset=UTF-8');
      $this->setContent ($content);
      return $this;
   }

   public function json (mixed $data) : self
   {
      $this->headers->set ('Content-Type', 'application/json');
      $this->setContent (json_encode ($data));
      return $this;
   }

   public function redirect (string $url, int $status = 302) : self
   {
      $this->headers->set ('Location', $url);
      $this->setStatusCode ($status);
      return $this;
   }

   public function status (int $code) : self
   {
      $this->setStatusCode ($code);
      return $this;
   }

   public function text (string $content) : self
   {
      $this->headers->set ('Content-Type', 'text/plain; charset=UTF-8');
      $this->setContent ($content);
      return $this;
   }

   public function warning (string $message, ?int $code = null) : self
   {
      container (Flash::class)->warning ($message, $code);
      return $this;
   }
}
