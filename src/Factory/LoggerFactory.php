<?php

namespace Wisp\Factory;

use Monolog\Handler\ErrorLogHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger as MonologLogger;
use Psr\Log\LoggerInterface;

class LoggerFactory
{
   public static function create (string $path, bool $debug = false) : LoggerInterface
   {
      $logger = new MonologLogger ('wisp');

      $logger->pushHandler (new StreamHandler (
         $path . '/wisp.log',
         $debug ? Level::Debug : Level::Warning
      ));

      if (PHP_SAPI === 'cli' ||
          PHP_SAPI === 'cli-server') {
         $logger->pushHandler (new StreamHandler (
            'php://stdout',
            $debug ? Level::Debug : Level::Info
         ));
      }

      $logger->pushHandler (new ErrorLogHandler (
         messageType: ErrorLogHandler::OPERATING_SYSTEM,
         level: Level::Critical,
         bubble: false
      ));

      return $logger;
   }
}
