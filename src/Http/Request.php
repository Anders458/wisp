<?php

namespace Wisp\Http;

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Wisp\Exception\JsonParseException;

class Request extends SymfonyRequest
{
   public static function createFrom (SymfonyRequest $request): self
   {
      return new self (
         $request->query->all (),
         $request->request->all (),
         $request->attributes->all (),
         $request->cookies->all (),
         $request->files->all (),
         $request->server->all (),
         $request->getContent ()
      );
   }

   public function input (string $key, mixed $default = null): mixed
   {
      if ($this->isJson ()) {
         $content = $this->getContent ();

         if (!empty ($content)) {
            $data = json_decode ($content, true);

            if (json_last_error () !== JSON_ERROR_NONE) {
               throw JsonParseException::fromLastError ();
            }

            if (is_array ($data) && array_key_exists ($key, $data)) {
               return $data [$key];
            }
         }
      }

      return $this->get ($key, $default);
   }

   public function all (): array
   {
      if ($this->isJson ()) {
         $content = $this->getContent ();

         if (empty ($content)) {
            return [];
         }

         $data = json_decode ($content, true);

         if (json_last_error () !== JSON_ERROR_NONE) {
            throw JsonParseException::fromLastError ();
         }

         return $data ?? [];
      }

      return array_merge ($this->query->all (), $this->request->all ());
   }

   public function only (array $keys): array
   {
      return array_intersect_key ($this->all (), array_flip ($keys));
   }

   public function except (array $keys): array
   {
      return array_diff_key ($this->all (), array_flip ($keys));
   }

   public function has (string $key): bool
   {
      return array_key_exists ($key, $this->all ());
   }

   public function ip (): ?string
   {
      return $this->getClientIp ();
   }

   public function userAgent (): ?string
   {
      return $this->headers->get ('User-Agent');
   }

   public function isJson (): bool
   {
      $contentType = $this->headers->get ('Content-Type', '');

      return str_contains ($contentType, 'application/json');
   }

   public function wantsJson (): bool
   {
      return str_contains ($this->headers->get ('Accept', ''), 'application/json');
   }

   public function bearerToken (): ?string
   {
      $header = $this->headers->get ('Authorization', '');

      if (str_starts_with ($header, 'Bearer ')) {
         return substr ($header, 7);
      }

      return null;
   }
}
