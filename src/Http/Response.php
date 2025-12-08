<?php

namespace Wisp\Http;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Wisp\Pagination\Pagination;
use Wisp\Service\Flash;

class Response extends \Symfony\Component\HttpFoundation\Response
{
   private static ?Flash $sharedFlash = null;
   private static ?Pagination $sharedPagination = null;

   /**
    * Set the shared Flash service (called by WispBundle during boot).
    *
    * @internal
    */
   public static function setSharedFlash (Flash $flash): void
   {
      self::$sharedFlash = $flash;
   }

   /**
    * Get and consume pagination (used by EnvelopeSubscriber).
    *
    * @internal
    */
   public static function consumePagination (): ?Pagination
   {
      $pagination = self::$sharedPagination;
      self::$sharedPagination = null;
      return $pagination;
   }

   /**
    * Add an error message to flash.
    */
   public function error (string $message, ?string $code = null): self
   {
      self::$sharedFlash?->error ($message, $code);
      $this->ensureJsonContentType ();
      return $this;
   }

   /**
    * Add a warning message to flash.
    */
   public function warning (string $message, ?string $code = null): self
   {
      self::$sharedFlash?->warning ($message, $code);
      $this->ensureJsonContentType ();
      return $this;
   }

   /**
    * Add an info message to flash.
    */
   public function info (string $message, ?string $code = null): self
   {
      self::$sharedFlash?->info ($message, $code);
      $this->ensureJsonContentType ();
      return $this;
   }

   /**
    * Add a success message to flash.
    */
   public function success (string $message, ?string $code = null): self
   {
      self::$sharedFlash?->success ($message, $code);
      $this->ensureJsonContentType ();
      return $this;
   }

   private function ensureJsonContentType (): void
   {
      if (!$this->headers->has ('Content-Type')) {
         $this->headers->set ('Content-Type', 'application/json');
      }
   }

   public function status (int $code): self
   {
      $this->setStatusCode ($code);
      return $this;
   }

   public function json (mixed $data): self
   {
      $this->headers->set ('Content-Type', 'application/json');
      $this->setContent (json_encode ($data, JSON_THROW_ON_ERROR));
      return $this;
   }

   /**
    * Set paginated JSON response.
    *
    * Usage:
    *   return (new Response)->paginated ($items, $pagination);
    */
   public function paginated (array $data, Pagination $pagination): self
   {
      self::$sharedPagination = $pagination;
      return $this->json ($data);
   }

   public function text (string $content): self
   {
      $this->headers->set ('Content-Type', 'text/plain; charset=UTF-8');
      $this->setContent ($content);
      return $this;
   }

   public function html (string $content): self
   {
      $this->headers->set ('Content-Type', 'text/html; charset=UTF-8');
      $this->setContent ($content);
      return $this;
   }

   public function empty (int $code = 204): self
   {
      $this->setStatusCode ($code);
      $this->setContent ('');
      return $this;
   }

   public function header (string $name, string $value): self
   {
      $this->headers->set ($name, $value);
      return $this;
   }

   public function vary (string|array $headers): self
   {
      $existing = $this->headers->get ('Vary');
      $headerList = is_array ($headers) ? $headers : [ $headers ];

      if ($existing !== null) {
         $headerList = array_merge (explode (', ', $existing), $headerList);
      }

      $this->headers->set ('Vary', implode (', ', array_unique ($headerList)));
      return $this;
   }

   public function cache (int|false $ttl, bool $public = false): self
   {
      if ($ttl === false) {
         $this->headers->set ('Cache-Control', 'no-store, no-cache, must-revalidate');
      } else {
         $this->headers->set ('Cache-Control', sprintf (
            '%s, max-age=%d',
            $public ? 'public' : 'private, must-revalidate',
            $ttl
         ));
         $this->headers->set ('Expires', gmdate ('D, d M Y H:i:s', time () + $ttl) . ' GMT');
      }

      return $this;
   }

   public function etag (): self
   {
      $content = $this->getContent ();

      if ($content !== false) {
         $this->headers->set ('ETag', '"' . md5 ($content) . '"');
      }

      return $this;
   }

   public function redirect (string $url, int $status = 302): RedirectResponse
   {
      return new RedirectResponse ($url, $status);
   }

   public function download (
      string $path,
      ?string $name = null,
      ?string $allowedDirectory = null
   ): BinaryFileResponse
   {
      $realPath = realpath ($path);

      if ($realPath === false || !file_exists ($realPath) || !is_readable ($realPath) || is_dir ($realPath)) {
         throw new \RuntimeException ("File not found or not readable: {$path}");
      }

      if ($allowedDirectory !== null) {
         $realAllowedDir = realpath ($allowedDirectory);

         if ($realAllowedDir === false || !str_starts_with ($realPath, $realAllowedDir . DIRECTORY_SEPARATOR)) {
            throw new \RuntimeException ("Access denied: file is outside allowed directory");
         }
      }

      $response = new BinaryFileResponse ($realPath);
      $response->setContentDisposition (
         ResponseHeaderBag::DISPOSITION_ATTACHMENT,
         $name ?? basename ($realPath)
      );

      return $response;
   }
}
