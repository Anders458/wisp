<?php

namespace Wisp\Service;

use Symfony\Component\HttpFoundation\Session\SessionInterface;

class Flash implements FlashInterface
{
   public array $errors = [];
   public array $warnings = [];

   public int $code = 0;

   public function __construct (
      private SessionInterface $session
   )
   {
      // Load current flash data from session (populate public properties for BC)
      $this->errors = $session->get ('wisp:flash.errors', []);
      $this->warnings = $session->get ('wisp:flash.warnings', []);
      $this->code = $session->get ('wisp:flash.code', 0);
   }

   public function clear () : self
   {
      $this->errors = [];
      $this->warnings = [];
      $this->code = 0;

      $this->session->remove ('wisp:flash.errors');
      $this->session->remove ('wisp:flash.warnings');
      $this->session->remove ('wisp:flash.code');

      return $this;
   }

   public function consume () : array
   {
      $data = [
         'errors' => $this->errors,
         'warnings' => $this->warnings,
         'code' => $this->code
      ];

      $this->clear ();

      return $data;
   }

   public function error (string $message, ?int $code = null) : self
   {
      $this->errors [] = $message;

      if ($code !== null && $code !== 0) {
         $this->code |= $code;
      }

      // Persist to session
      $this->session->set ('wisp:flash.errors', $this->errors);
      $this->session->set ('wisp:flash.code', $this->code);

      return $this;
   }

   public function warning (string $message, ?int $code = null) : self
   {
      $this->warnings [] = $message;

      if ($code !== null && $code !== 0) {
         $this->code |= $code;
      }

      // Persist to session
      $this->session->set ('wisp:flash.warnings', $this->warnings);
      $this->session->set ('wisp:flash.code', $this->code);

      return $this;
   }
}