<?php

namespace Wisp\Service;

interface FlashInterface
{
   public function clear () : self;

   public function error (string $message, ?int $code = null) : self;

   public function warning (string $message, ?int $code = null) : self;
}
