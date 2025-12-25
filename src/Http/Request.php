<?php

namespace Wisp\Http;

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Wisp\Exception\JsonParseException;
use Wisp\Exception\ValidationException;
use Wisp\Pagination\Pagination;
use Wisp\Security\BearerDecoderInterface;

class Request extends SymfonyRequest
{
   private static ?ValidatorInterface $sharedValidator = null;
   private static ?BearerDecoderInterface $sharedBearerDecoder = null;
   private ?array $decodedBearer = null;
   private bool $bearerDecoded = false;

   /**
    * Set the shared Validator service (called by WispBundle).
    *
    * @internal
    */
   public static function setSharedValidator (ValidatorInterface $validator): void
   {
      self::$sharedValidator = $validator;
   }

   /**
    * Set the shared BearerDecoder service (called by WispBundle).
    *
    * @internal
    */
   public static function setSharedBearerDecoder (?BearerDecoderInterface $decoder): void
   {
      self::$sharedBearerDecoder = $decoder;
   }

   /**
    * Validate request data against a DTO class.
    *
    * @template T of object
    * @param class-string<T> $dtoClass
    * @return T
    * @throws ValidationException
    */
   public function validate (string $dtoClass): object
   {
      if (self::$sharedValidator === null) {
         throw new \RuntimeException ('Validator not configured. Ensure WispBundle is properly loaded.');
      }

      $data = $this->all ();
      $dto = $this->hydrateDto ($dtoClass, $data);
      $violations = self::$sharedValidator->validate ($dto);

      if (count ($violations) > 0) {
         throw new ValidationException ($violations);
      }

      return $dto;
   }

   /**
    * @template T of object
    * @param class-string<T> $dtoClass
    * @return T
    */
   private function hydrateDto (string $dtoClass, array $data): object
   {
      $reflection = new \ReflectionClass ($dtoClass);
      $dto = $reflection->newInstanceWithoutConstructor ();

      foreach ($reflection->getProperties () as $property) {
         if ($property->isPublic ()) {
            $name = $property->getName ();

            if (array_key_exists ($name, $data)) {
               $property->setValue ($dto, $data [$name]);
            }
         }
      }

      return $dto;
   }
   public static function createFrom (SymfonyRequest $request): self
   {
      $instance = new self (
         $request->query->all (),
         $request->request->all (),
         $request->attributes->all (),
         $request->cookies->all (),
         $request->files->all (),
         $request->server->all (),
         $request->getContent ()
      );

      // Copy session if available
      if ($request->hasSession ()) {
         $instance->setSession ($request->getSession ());
      }

      return $instance;
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

      return $this->query->get ($key) ?? $this->request->get ($key, $default);
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

   /**
    * Get the raw bearer token string.
    */
   public function bearerRaw (): ?string
   {
      $header = $this->headers->get ('Authorization', '');

      if (str_starts_with ($header, 'Bearer ')) {
         return substr ($header, 7);
      }

      return null;
   }

   /**
    * Get decoded bearer token claims, or a specific claim value.
    *
    * @param string|null $claim Specific claim to retrieve (null = all claims)
    * @param mixed $default Default value if claim not found
    * @return mixed All claims array, specific claim value, or default
    */
   public function bearer (?string $claim = null, mixed $default = null): mixed
   {
      $claims = $this->decodeBearerToken ();

      if ($claims === null) {
         return $claim === null ? null : $default;
      }

      if ($claim === null) {
         return $claims;
      }

      return $claims [$claim] ?? $default;
   }

   /**
    * Check if the bearer token is present and valid.
    */
   public function bearerValid (): bool
   {
      return $this->decodeBearerToken () !== null;
   }

   private function decodeBearerToken (): ?array
   {
      // Cache the decoded result
      if ($this->bearerDecoded) {
         return $this->decodedBearer;
      }

      $this->bearerDecoded = true;
      $token = $this->bearerRaw ();

      if ($token === null || self::$sharedBearerDecoder === null) {
         return $this->decodedBearer = null;
      }

      return $this->decodedBearer = self::$sharedBearerDecoder->decode ($token);
   }

   /**
    * Create pagination from query parameters.
    *
    * Auto-detects offset vs cursor mode:
    *   ?page=2&limit=20       → offset mode
    *   ?cursor=abc&limit=20   → cursor mode
    */
   public function paginate (int $defaultLimit = 20): Pagination
   {
      return Pagination::fromQuery ($this->query->all (), $defaultLimit);
   }
}
