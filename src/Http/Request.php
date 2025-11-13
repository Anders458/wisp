<?php

namespace Wisp\Http;

use ReflectionClass;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
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

   /**
    * Get an input value from the request (query, body, or JSON)
    *
    * @param string $key The parameter name
    * @param mixed $default Default value if not found
    * @return mixed
    */
   public function input (string $key, mixed $default = null) : mixed
   {
      // Check JSON body first
      if ($this->headers->get ('Content-Type') === 'application/json' ||
          str_contains ($this->headers->get ('Content-Type', ''), 'application/json')) {
         $data = json_decode ($this->getContent (), true);
         if (is_array ($data) && array_key_exists ($key, $data)) {
            return $data [$key];
         }
      }

      // Fall back to Symfony's get() which checks request and query parameters
      return $this->get ($key, $default);
   }

   public function validate (string $dtoClass) : object
   {
      // Get request data based on content type
      $data = $this->getRequestData ();

      // Get validator from container
      $validator = Wisp::container ()->get (ValidatorInterface::class);

      // Create and hydrate DTO instance
      $dto = $this->hydrateDto ($dtoClass, $data);

      // Validate the DTO
      $violations = $validator->validate ($dto);

      if (count ($violations) > 0) {
         throw new ValidationException ($violations);
      }

      return $dto;
   }

   private function getRequestData () : array
   {
      // Check if request is JSON
      if ($this->headers->get ('Content-Type') === 'application/json' ||
          str_contains ($this->headers->get ('Content-Type', ''), 'application/json')) {
         $data = json_decode ($this->getContent (), true);
         return $data ?? [];
      }

      // Otherwise, merge query and request parameters
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
