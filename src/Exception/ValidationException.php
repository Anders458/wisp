<?php

namespace Wisp\Exception;

use Symfony\Component\Validator\ConstraintViolationListInterface;

class ValidationException extends \Exception
{
   public function __construct (
      private ConstraintViolationListInterface $violations,
      string $message = 'Validation failed',
      int $code = 422
   )
   {
      parent::__construct ($message, $code);
   }

   public function getViolations (): ConstraintViolationListInterface
   {
      return $this->violations;
   }
}
