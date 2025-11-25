<?php

use Wisp\Testing\WispTestCase;
use Wisp\Wisp;

require_once __DIR__ . '/Fixtures.php';

$exampleApp = require __DIR__ . '/../index.php';

if (!$exampleApp instanceof Wisp) {
   throw new RuntimeException ('example/index.php must return a Wisp instance');
}

uses ()->beforeEach (function () use ($exampleApp) {
   $this->client = new WispTestCase ($exampleApp);
})->in ('Feature');
