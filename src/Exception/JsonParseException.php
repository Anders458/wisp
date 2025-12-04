<?php

namespace Wisp\Exception;

class JsonParseException extends \Exception
{
   public static function fromLastError (): self
   {
      return new self (
         json_last_error_msg (),
         json_last_error ()
      );
   }
}
