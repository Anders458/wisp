<?php

namespace Wisp\Testing;

use Faker\Factory as FakerFactory;
use Faker\Generator;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints as Assert;

class FixtureFactory
{
   private Generator $faker;

   public function __construct ()
   {
      $this->faker = FakerFactory::create ();
   }

   public function create (string $class, array $overrides = []): array
   {
      $reflection = new ReflectionClass ($class);
      $properties = $reflection->getProperties (ReflectionProperty::IS_PUBLIC);
      $fixture = [];

      foreach ($properties as $property) {
         $name = $property->getName ();

         if (array_key_exists ($name, $overrides)) {
            $fixture [$name] = $overrides [$name];
            continue;
         }

         if ($property->hasDefaultValue ()) {
            $default = $property->getDefaultValue ();
            if ($default !== null) {
               $fixture [$name] = $default;
               continue;
            }
         }

         $attributes = $property->getAttributes ();
         $fixture [$name] = $this->generateValueFromAttributes ($attributes, $property);
      }

      return $fixture;
   }

   private function generateValueFromAttributes (array $attributes, ReflectionProperty $property): mixed
   {
      $constraints = [];

      foreach ($attributes as $attribute) {
         $instance = $attribute->newInstance ();
         if ($instance instanceof Constraint) {
            $constraints [] = $instance;
         }
      }

      if (empty ($constraints)) {
         return $this->generateFromType ($property);
      }

      return $this->generateFromConstraints ($constraints, $property);
   }

   private function generateFromConstraints (array $constraints, ReflectionProperty $property): mixed
   {
      $type = $property->getType ()?->getName ();

      foreach ($constraints as $constraint) {
         if ($constraint instanceof Assert\Email) {
            return $this->faker->email ();
         }

         if ($constraint instanceof Assert\Choice) {
            return $constraint->choices [0] ?? null;
         }

         if ($constraint instanceof Assert\Range) {
            $min = $constraint->min ?? 0;
            $max = $constraint->max ?? 100;
            return $type === 'int'
               ? $this->faker->numberBetween ($min, $max)
               : $this->faker->randomFloat (2, $min, $max);
         }

         if ($constraint instanceof Assert\Length) {
            $min = $constraint->min ?? 1;
            $max = $constraint->max ?? 20;
            return $this->faker->regexify ('[a-zA-Z0-9]{' . $min . ',' . $max . '}');
         }

         if ($constraint instanceof Assert\Regex) {
            return $this->faker->regexify ($constraint->pattern);
         }
      }

      return $this->generateFromType ($property);
   }

   private function generateFromType (ReflectionProperty $property): mixed
   {
      $type = $property->getType ()?->getName ();

      return match ($type) {
         'string' => $this->faker->word (),
         'int' => $this->faker->numberBetween (1, 100),
         'float' => $this->faker->randomFloat (2, 0, 100),
         'bool' => $this->faker->boolean (),
         'array' => [],
         default => null,
      };
   }
}
