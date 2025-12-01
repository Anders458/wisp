<?php

namespace Wisp\Exception;

class JsonParseException extends \RuntimeException
{
   public static function fromLastError (): self
   {
      return new self (
         'JSON parse error: ' . json_last_error_msg (),
         json_last_error ()
      );
   }
}
