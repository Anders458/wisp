<?php

namespace Wisp\Http;

use ReflectionClass;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Wisp\Exception\JsonParseException;
use Wisp\Wisp;

class Request extends SymfonyRequest
{

   public function forward (array $controller, array $attributes = []) : Response
   {
      $kernel = Wisp::container ()->get (HttpKernelInterface::class);

      $attributes ['_controller'] = $controller;

      $subRequest = $this->duplicate (
         null,
         null,
         $attributes
      );

      $response = $kernel->handle ($subRequest, HttpKernelInterface::SUB_REQUEST);

      return $response;
   }

   public function input (string $key, mixed $default = null) : mixed
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

   public function ip () : ?string
   {
      return $this->getClientIp ();
   }

   public function isJson () : bool
   {
      $contentType = $this->headers->get ('Content-Type', '');
      return $contentType === 'application/json' || str_contains ($contentType, 'application/json');
   }

   public function userAgent () : ?string
   {
      return $this->headers->get ('User-Agent');
   }

   public function validate (string $dtoClass) : object
   {
      $data = $this->getRequestData ();
      $validator = Wisp::container ()->get (ValidatorInterface::class);
      $dto = $this->hydrateDto ($dtoClass, $data);
      $violations = $validator->validate ($dto);

      if (count ($violations) > 0) {
         throw new ValidationException ($violations);
      }

      return $dto;
   }

   private function getRequestData () : array
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

   private function hydrateDto (string $dtoClass, array $data) : object
   {
      $reflection = new ReflectionClass ($dtoClass);
      $dto = $reflection->newInstance ();

      // Set public properties from data
      foreach ($reflection->getProperties () as $property) {
         if ($property->isPublic ()) {
            $propertyName = $property->getName ();

            if (array_key_exists ($propertyName, $data)) {
               $property->setValue ($dto, $data [$propertyName]);
            }
         }
      }

      return $dto;
   }
}
