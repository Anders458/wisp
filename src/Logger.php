<?php

namespace Wisp;

use Monolog\Handler\ErrorLogHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger as MonologLogger;

class Logger extends MonologLogger
{
   public function __construct (string $logsPath, bool $debug = false)
   {
      parent::__construct ('wisp');

      $this->pushHandler (new StreamHandler (
         $logsPath . '/wisp.log',
         $debug ? Level::Debug : Level::Warning
      ));

      $this->pushHandler (new ErrorLogHandler (
         messageType: ErrorLogHandler::OPERATING_SYSTEM,
         level: Level::Critical,
         bubble: false
      ));
   }
}
