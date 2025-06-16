<?php

namespace Wisp\Util;

use Psr\Log\AbstractLogger;
use Wisp\Environment\Runtime;

class Logger extends AbstractLogger
{
   public function log ($level, string | \Stringable $message, array $context = []) : void
   {
      if (!container (Runtime::class)->isDebug ()) {
         return;
      }

      $message = strtr ($message, array_map (
         fn ($v) => @ (string) $v,
         
         array_combine (
            array_map (fn ($k) => '{' . $k . '}', array_keys ($context)),
            $context
         )
      ));

      echo "[" . ucfirst ($level) . "] $message";
      
      if (!empty ($context)) {
         echo ' ' . json_encode ($context, container (Runtime::class)->isCli () ? 0 : JSON_PRETTY_PRINT);
      }
      
      echo PHP_EOL;
   }
}