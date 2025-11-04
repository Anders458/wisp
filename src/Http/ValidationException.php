<?php

namespace Wisp\Http;

use Exception;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Wisp\Service\Flash;

class ValidationException extends Exception
{
   public function __construct (
      private ConstraintViolationListInterface $violations
   )
   {
      parent::__construct ('Validation failed', 422);
   }

   public function getResponse () : Response
   {
      $response = new Response ();
      $flash = container (Flash::class);

      // Add each violation to flash errors
      foreach ($this->violations as $violation) {
         $field = $violation->getPropertyPath ();
         $message = $violation->getMessage ();
         $flash->error ("{$field}: {$message}");
      }

      return $response
         ->status (422)
         ->json (null);
   }

   public function getViolations () : ConstraintViolationListInterface
   {
      return $this->violations;
   }
}
