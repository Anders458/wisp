<?php

namespace Wisp\Service;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\ConstraintViolationListInterface;

class Flash
{
   /** @var array<int, array{propertyPath: string, title: string}> */
   private array $violations = [];

   /** @var array<int, array{code: ?string, message: string}> */
   private array $errors = [];

   /** @var array<int, array{code: ?string, message: string}> */
   private array $warnings = [];

   /** @var array<int, array{code: ?string, message: string}> */
   private array $info = [];

   /** @var array<int, array{code: ?string, message: string}> */
   private array $success = [];

   public function __construct (
      private RequestStack $requestStack
   )
   {
      $this->loadFromSession ();
   }

   /**
    * Add a validation violation (for input validation errors).
    */
   public function violation (string $propertyPath, string $title): self
   {
      $this->violations [] = [
         'propertyPath' => $propertyPath,
         'title' => $title
      ];

      $this->persistToSession ();

      return $this;
   }

   /**
    * Add violations from a Symfony ConstraintViolationList.
    */
   public function violations (ConstraintViolationListInterface $list): self
   {
      foreach ($list as $violation) {
         $this->violations [] = [
            'propertyPath' => $violation->getPropertyPath (),
            'title' => (string) $violation->getMessage ()
         ];
      }

      $this->persistToSession ();

      return $this;
   }

   /**
    * Add an error message (for non-validation errors).
    */
   public function error (string $message, ?string $code = null): self
   {
      $this->errors [] = $this->formatMessage ($code, $message);
      $this->persistToSession ();

      return $this;
   }

   /**
    * Add a warning message.
    */
   public function warning (string $message, ?string $code = null): self
   {
      $this->warnings [] = $this->formatMessage ($code, $message);
      $this->persistToSession ();

      return $this;
   }

   /**
    * Add an info message.
    */
   public function info (string $message, ?string $code = null): self
   {
      $this->info [] = $this->formatMessage ($code, $message);
      $this->persistToSession ();

      return $this;
   }

   /**
    * Add a success message.
    */
   public function success (string $message, ?string $code = null): self
   {
      $this->success [] = $this->formatMessage ($code, $message);
      $this->persistToSession ();

      return $this;
   }

   public function clear (): self
   {
      $this->violations = [];
      $this->errors = [];
      $this->warnings = [];
      $this->info = [];
      $this->success = [];

      $session = $this->getSession ();

      if ($session !== null) {
         $session->remove ('wisp:flash');
      }

      return $this;
   }

   /**
    * Consume and return all flash data, clearing it afterward.
    */
   public function consume (): array
   {
      $data = [];

      if (!empty ($this->violations)) {
         $data ['violations'] = $this->violations;
      }

      if (!empty ($this->errors)) {
         $data ['errors'] = $this->errors;
      }

      if (!empty ($this->warnings)) {
         $data ['warnings'] = $this->warnings;
      }

      if (!empty ($this->info)) {
         $data ['info'] = $this->info;
      }

      if (!empty ($this->success)) {
         $data ['success'] = $this->success;
      }

      $this->clear ();

      return $data;
   }

   public function hasViolations (): bool
   {
      return !empty ($this->violations);
   }

   public function hasErrors (): bool
   {
      return !empty ($this->errors);
   }

   public function hasWarnings (): bool
   {
      return !empty ($this->warnings);
   }

   public function hasMessages (): bool
   {
      return !empty ($this->violations)
         || !empty ($this->errors)
         || !empty ($this->warnings)
         || !empty ($this->info)
         || !empty ($this->success);
   }

   /**
    * @return array<int, array{propertyPath: string, title: string}>
    */
   public function getViolations (): array
   {
      return $this->violations;
   }

   /**
    * @return array<int, array{code: ?string, message: string}>
    */
   public function getErrors (): array
   {
      return $this->errors;
   }

   /**
    * @return array<int, array{code: ?string, message: string}>
    */
   public function getWarnings (): array
   {
      return $this->warnings;
   }

   /**
    * @return array{code: ?string, message: string}
    */
   private function formatMessage (?string $code, string $message): array
   {
      $item = [ 'message' => $message ];

      if ($code !== null) {
         $item ['code'] = $code;
      }

      return $item;
   }

   private function loadFromSession (): void
   {
      $session = $this->getSession ();

      if ($session === null) {
         return;
      }

      $data = $session->get ('wisp:flash', []);
      $this->violations = $data ['violations'] ?? [];
      $this->errors = $data ['errors'] ?? [];
      $this->warnings = $data ['warnings'] ?? [];
      $this->info = $data ['info'] ?? [];
      $this->success = $data ['success'] ?? [];
   }

   private function persistToSession (): void
   {
      $session = $this->getSession ();

      if ($session === null) {
         return;
      }

      $session->set ('wisp:flash', [
         'violations' => $this->violations,
         'errors' => $this->errors,
         'warnings' => $this->warnings,
         'info' => $this->info,
         'success' => $this->success
      ]);
   }

   private function getSession (): ?\Symfony\Component\HttpFoundation\Session\SessionInterface
   {
      $request = $this->requestStack->getCurrentRequest ();

      if ($request === null || !$request->hasSession ()) {
         return null;
      }

      return $request->getSession ();
   }
}
