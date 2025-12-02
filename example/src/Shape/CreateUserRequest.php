<?php

namespace App\Shape;

use Symfony\Component\Validator\Constraints as Assert;

class CreateUserRequest
{
   #[Assert\NotBlank]
   #[Assert\Email]
   public string $email;

   #[Assert\NotBlank]
   #[Assert\Length (min: 8, max: 100)]
   public string $password;

   #[Assert\NotBlank]
   #[Assert\Length (min: 2, max: 50)]
   public string $name;
}
