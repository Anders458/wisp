<?php

namespace Wisp\Http;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Wisp\Service\FlashInterface;

class Response extends SymfonyResponse
{
   public function body (string $content) : self
   {
      $this->setContent ($content);
      return $this;
   }

   public function cache (int | false $ttl, bool $public = false) : self
   {
      if ($ttl === false) {
         $this->headers->set ('Cache-Control', 'no-store, no-cache, must-revalidate');
      } else {
         $this->headers->set ('Cache-Control', sprintf ('%s, max-age=%d', $public ? 'public' : 'private, must-revalidate', $ttl));
         $this->headers->set ('Expires', gmdate ('D, d M Y H:i:s', time () + $ttl) . ' GMT');
      }

      return $this;
   }

   public function download (string $path, ?string $name = null) : BinaryFileResponse
   {
      if (!file_exists ($path) || !is_readable ($path)) {
         throw new \RuntimeException ("File not found: {$path}");
      }
      
      $response = new BinaryFileResponse ($path);
      $response->setContentDisposition (
         ResponseHeaderBag::DISPOSITION_ATTACHMENT,
         $name ?? basename ($path)
      );
      
      return $response;
   }

   public function error (string $message, ?int $code = null) : self
   {
      container (FlashInterface::class)->error ($message, $code);

      // Set JSON content type so Envelope middleware will wrap the response
      if (!$this->headers->has ('Content-Type')) {
         $this->headers->set ('Content-Type', 'application/json');
      }

      return $this;
   }

   public function etag () : self
   {
      $this->headers->set ('ETag', '"' . md5 ($this->getContent ()) . '"');
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
      $this->setContent (json_encode ($data, JSON_THROW_ON_ERROR));
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

   public function vary (string | array $headers) : self
   {
      $headers = array_map ('trim', (array) $headers);
      $existing = $this->headers->get ('Vary', '');

      if ($existing) {
         $existingHeaders = array_map ('trim', explode (',', $existing));
         $headers = array_merge ($existingHeaders, $headers);
      }

      $this->headers->set ('Vary', implode (', ', array_unique ($headers)));
      return $this;
   }

   public function warning (string $message, ?int $code = null) : self
   {
      container (FlashInterface::class)->warning ($message, $code);

      // Set JSON content type so Envelope middleware will wrap the response
      if (!$this->headers->has ('Content-Type')) {
         $this->headers->set ('Content-Type', 'application/json');
      }

      return $this;
   }
}
