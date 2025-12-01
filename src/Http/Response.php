<?php

namespace Wisp\Http;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class Response extends \Symfony\Component\HttpFoundation\Response
{
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

   public function error (string $message, int $code = 400): self
   {
      return $this->status ($code)->json ([ 'error' => $message ]);
   }

   public function header (string $name, string $value): self
   {
      $this->headers->set ($name, $value);
      return $this;
   }

   public function cache (int | false $ttl, bool $public = false): self
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
