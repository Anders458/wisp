<?php

namespace Wisp\Exception;

use Symfony\Component\Validator\ConstraintViolationListInterface;

class ValidationException extends \RuntimeException
{
   public function __construct (
      public readonly ConstraintViolationListInterface $violations
   )
   {
      $messages = [];

      foreach ($violations as $violation) {
         $messages [] = sprintf (
            '%s: %s',
            $violation->getPropertyPath (),
            $violation->getMessage ()
         );
      }

      parent::__construct (implode ('; ', $messages));
   }

   public function toArray (): array
   {
      $errors = [];

      foreach ($this->violations as $violation) {
         $path = $violation->getPropertyPath () ?: '_root';
         $errors [$path] [] = $violation->getMessage ();
      }

      return $errors;
   }
}
