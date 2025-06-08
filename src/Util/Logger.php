<?php

namespace Wisp\Util;

use Psr\Log\AbstractLogger;

class Logger extends AbstractLogger
{
   public function log ($level, string | \Stringable $message, array $context = []) : void
   {
      $message = strtr ($message, array_map (
         fn ($v) => (string) $v,
         
         array_combine (
            array_map (fn ($k) => '{' . $k . '}', array_keys ($context)),
            $context
         )
      ));

      echo "[$level] $message";
      
      if (!empty ($context)) {
         echo ' ' . json_encode ($context);
      }
      
      echo PHP_EOL;
   }
}