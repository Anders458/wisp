<?php

namespace Wisp\Service;

use Symfony\Component\HttpFoundation\RequestStack;

class Flash
{
   /** @var string[] */
   private array $errors = [];

   /** @var string[] */
   private array $warnings = [];

   /** @var string[] */
   private array $info = [];

   /** @var string[] */
   private array $success = [];

   private int $code = 0;

   public function __construct (
      private RequestStack $requestStack
   )
   {
      $this->loadFromSession ();
   }

   public function error (string $message, ?int $code = null): self
   {
      $this->errors [] = $message;

      if ($code !== null && $code !== 0) {
         $this->code |= $code;
      }

      $this->persistToSession ();

      return $this;
   }

   public function warning (string $message, ?int $code = null): self
   {
      $this->warnings [] = $message;

      if ($code !== null && $code !== 0) {
         $this->code |= $code;
      }

      $this->persistToSession ();

      return $this;
   }

   public function info (string $message): self
   {
      $this->info [] = $message;
      $this->persistToSession ();

      return $this;
   }

   public function success (string $message): self
   {
      $this->success [] = $message;
      $this->persistToSession ();

      return $this;
   }

   public function clear (): self
   {
      $this->errors = [];
      $this->warnings = [];
      $this->info = [];
      $this->success = [];
      $this->code = 0;

      $session = $this->getSession ();

      if ($session !== null) {
         $session->remove ('wisp:flash.errors');
         $session->remove ('wisp:flash.warnings');
         $session->remove ('wisp:flash.info');
         $session->remove ('wisp:flash.success');
         $session->remove ('wisp:flash.code');
      }

      return $this;
   }

   public function consume (): array
   {
      $data = [
         'errors' => $this->errors,
         'warnings' => $this->warnings,
         'info' => $this->info,
         'success' => $this->success,
         'code' => $this->code
      ];

      $this->clear ();

      return $data;
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
      return !empty ($this->errors)
         || !empty ($this->warnings)
         || !empty ($this->info)
         || !empty ($this->success);
   }

   /**
    * @return string[]
    */
   public function getErrors (): array
   {
      return $this->errors;
   }

   /**
    * @return string[]
    */
   public function getWarnings (): array
   {
      return $this->warnings;
   }

   public function getCode (): int
   {
      return $this->code;
   }

   private function loadFromSession (): void
   {
      $session = $this->getSession ();

      if ($session === null) {
         return;
      }

      $this->errors = $session->get ('wisp:flash.errors', []);
      $this->warnings = $session->get ('wisp:flash.warnings', []);
      $this->info = $session->get ('wisp:flash.info', []);
      $this->success = $session->get ('wisp:flash.success', []);
      $this->code = $session->get ('wisp:flash.code', 0);
   }

   private function persistToSession (): void
   {
      $session = $this->getSession ();

      if ($session === null) {
         return;
      }

      $session->set ('wisp:flash.errors', $this->errors);
      $session->set ('wisp:flash.warnings', $this->warnings);
      $session->set ('wisp:flash.info', $this->info);
      $session->set ('wisp:flash.success', $this->success);
      $session->set ('wisp:flash.code', $this->code);
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
