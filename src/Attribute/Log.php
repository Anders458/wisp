<?php

namespace Wisp\Attribute;

use Attribute;

/**
 * Log API requests and responses with optional redaction.
 *
 * #[Log]
 * #[Log (level: 'debug')]
 * #[Log (redact: [ 'password', 'ssn' ], include: [ 'request', 'response' ])]
 */
#[Attribute (Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Log
{
   /**
    * @param string   $level   Log level: debug, info, warning, error
    * @param string[] $redact  Keys to mask in request/response
    * @param string[] $include What to log: request, response, headers, user
    */
   public function __construct (
      public string $level = 'info',
      public array $redact = [],
      public array $include = []
   ) {}
}
