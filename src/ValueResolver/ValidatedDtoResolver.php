<?php

namespace Wisp\ValueResolver;

use ReflectionClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Wisp\Attribute\Validated;
use Wisp\Exception\JsonParseException;
use Wisp\Exception\ValidationException;

class ValidatedDtoResolver implements ValueResolverInterface
{
   public function __construct (
      private ValidatorInterface $validator
   )
   {
   }

   public function resolve (Request $request, ArgumentMetadata $argument): iterable
   {
      $attributes = $argument->getAttributes (Validated::class, ArgumentMetadata::IS_INSTANCEOF);

      if (empty ($attributes)) {
         return [];
      }

      $type = $argument->getType ();

      if ($type === null || !class_exists ($type)) {
         return [];
      }

      /** @var ValidatedDto $attribute */
      $attribute = $attributes [0];

      $data = $this->getRequestData ($request);
      $dto = $this->hydrateDto ($type, $data);

      $violations = $this->validator->validate ($dto);

      if (count ($violations) > 0) {
         throw new ValidationException ($violations);
      }

      return [ $dto ];
   }

   private function getRequestData (Request $request): array
   {
      $contentType = $request->headers->get ('Content-Type', '');

      if (str_contains ($contentType, 'application/json')) {
         $content = $request->getContent ();

         if (empty ($content)) {
            return [];
         }

         $data = json_decode ($content, true);

         if (json_last_error () !== JSON_ERROR_NONE) {
            throw JsonParseException::fromLastError ();
         }

         return $data ?? [];
      }

      return array_merge ($request->query->all (), $request->request->all ());
   }

   private function hydrateDto (string $dtoClass, array $data): object
   {
      $reflection = new ReflectionClass ($dtoClass);

      // Check if class has a constructor with parameters
      $constructor = $reflection->getConstructor ();

      if ($constructor !== null && $constructor->getNumberOfParameters () > 0) {
         return $this->hydrateViaConstructor ($reflection, $constructor, $data);
      }

      // Otherwise, use property assignment
      return $this->hydrateViaProperties ($reflection, $data);
   }

   private function hydrateViaConstructor (
      ReflectionClass $reflection,
      \ReflectionMethod $constructor,
      array $data
   ): object
   {
      $args = [];

      foreach ($constructor->getParameters () as $param) {
         $name = $param->getName ();

         if (array_key_exists ($name, $data)) {
            $args [] = $data [$name];
         } elseif ($param->isDefaultValueAvailable ()) {
            $args [] = $param->getDefaultValue ();
         } elseif ($param->allowsNull ()) {
            $args [] = null;
         } else {
            $args [] = null; // Let validator catch this
         }
      }

      return $reflection->newInstanceArgs ($args);
   }

   private function hydrateViaProperties (ReflectionClass $reflection, array $data): object
   {
      $dto = $reflection->newInstance ();

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
