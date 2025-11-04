<?php

namespace Wisp\Example\Request;

use Symfony\Component\Validator\Constraints as Assert;

class HeroRequest
{
   #[Assert\NotBlank (message: 'Name is required')]
   #[Assert\Length (
      min: 3,
      max: 100,
      minMessage: 'Name must be at least {{ limit }} characters',
      maxMessage: 'Name cannot exceed {{ limit }} characters'
   )]
   public string $name;

   #[Assert\NotBlank (message: 'Power level is required')]
   #[Assert\Type (type: 'integer', message: 'Power level must be an integer')]
   #[Assert\Range (
      min: 1,
      max: 9999,
      notInRangeMessage: 'Power level must be between {{ min }} and {{ max }}'
   )]
   public int $power;

   #[Assert\Length (max: 500, maxMessage: 'Bio cannot exceed {{ limit }} characters')]
   public ?string $bio = null;

   #[Assert\Choice (
      choices: [ 'hero', 'villain', 'neutral' ],
      message: 'Alignment must be one of: {{ choices }}'
   )]
   public string $alignment = 'hero';
}
