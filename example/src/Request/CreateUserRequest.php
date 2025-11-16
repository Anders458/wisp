<?php

namespace Example\Request;

use Symfony\Component\Validator\Constraints as Assert;

class CreateUserRequest
{
   #[Assert\NotBlank (message: 'Email is required')]
   #[Assert\Email (message: 'Invalid email format')]
   public string $email;

   #[Assert\NotBlank (message: 'Username is required')]
   #[Assert\Length (
      min: 3,
      max: 20,
      minMessage: 'Username must be at least {{ limit }} characters',
      maxMessage: 'Username cannot be longer than {{ limit }} characters'
   )]
   #[Assert\Regex (
      pattern: '/^[a-zA-Z0-9_]+$/',
      message: 'Username can only contain letters, numbers, and underscores'
   )]
   public string $username;

   #[Assert\NotBlank (message: 'Password is required')]
   #[Assert\Length (
      min: 8,
      minMessage: 'Password must be at least {{ limit }} characters'
   )]
   #[Assert\Regex (
      pattern: '/[A-Z]/',
      message: 'Password must contain at least one uppercase letter'
   )]
   #[Assert\Regex (
      pattern: '/[a-z]/',
      message: 'Password must contain at least one lowercase letter'
   )]
   #[Assert\Regex (
      pattern: '/[0-9]/',
      message: 'Password must contain at least one number'
   )]
   public string $password;

   #[Assert\NotBlank (message: 'Age is required')]
   #[Assert\Type (type: 'integer', message: 'Age must be a number')]
   #[Assert\Range (
      min: 18,
      max: 120,
      notInRangeMessage: 'Age must be between {{ min }} and {{ max }}'
   )]
   public int $age;

   #[Assert\Choice (
      choices: ['user', 'admin', 'moderator'],
      message: 'Role must be one of: {{ choices }}'
   )]
   public ?string $role = 'user';
}
