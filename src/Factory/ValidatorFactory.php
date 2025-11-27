<?php

namespace Wisp\Factory;

use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ValidatorFactory
{
   public static function create () : ValidatorInterface
   {
      return Validation::createValidatorBuilder ()
         ->enableAttributeMapping ()
         ->getValidator ();
   }
}
