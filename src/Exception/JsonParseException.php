<?php

namespace Wisp\Exception;

class JsonParseException extends \JsonException
{
   public function __construct (string $message = 'Invalid JSON payload', int $code = 0, ?\Throwable $previous = null)
   {
      parent::__construct ($message, $code, $previous);
   }

   public static function fromLastError () : self
   {
      return new self ('Invalid JSON payload: ' . json_last_error_msg (), json_last_error ());
   }
}
