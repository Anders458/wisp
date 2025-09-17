<?php

namespace Wisp\Util;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use Wisp\Environment\Runtime;

class Logger extends AbstractLogger
{
   public function log ($level, string | \Stringable $message, array $context = []) : void
   {
      $message = strtr ($message, array_map (
         fn ($v) => @ (string) $v,
         
         array_combine (
            array_map (fn ($k) => '{' . $k . '}', array_keys ($context)),
            $context
         )
      ));

      $message = "[" . ucfirst ($level) . "] $message";
      
      if ($level === LogLevel::NOTICE ||
          $level === LogLevel::WARNING ||
          $level === LogLevel::ERROR ||
          $level === LogLevel::CRITICAL ||
          $level === LogLevel::ALERT ||
          $level === LogLevel::EMERGENCY) {
         error_log ($message . ' ' . json_encode ($context, JSON_PRETTY_PRINT));
      }

      if (!container (Runtime::class)->isDebug ()) {
         return;
      }

      echo $message;

      if (!empty ($context)) {
         echo ' ' . json_encode ($context, container (Runtime::class)->isCli () ? 0 : JSON_PRETTY_PRINT);
      }
      
      echo PHP_EOL;
   }
}