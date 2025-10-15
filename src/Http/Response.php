<?php

namespace Wisp\Http;

use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class Response extends SymfonyResponse
{
   public function getStatus () : string
   {
      return self::$statusTexts [$this->getStatusCode ()] ?? 'Unknown';
   }

   public function json (mixed $data) : self
   {
      $this->headers->set ('Content-Type', 'application/json');
      $this->setContent (json_encode ($data));
      return $this;
   }

   public function body (string $content) : self
   {
      $this->setContent ($content);
      return $this;
   }

   public function status (int $code) : self
   {
      $this->setStatusCode ($code);
      return $this;
   }
}
