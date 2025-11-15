<?php

namespace Wisp\Http;

use Exception;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Wisp\Service\FlashInterface;

class ValidationException extends Exception
{
   public function __construct (
      public readonly ConstraintViolationListInterface $violations
   )
   {
      parent::__construct ('Validation failed', 422);
   }

   public function getResponse () : Response
   {
      $response = new Response ();
      $flash = container (FlashInterface::class);

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
