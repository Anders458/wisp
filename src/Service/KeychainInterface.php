<?php

namespace Wisp\Service;

interface KeychainInterface
{
   public function get (string $name) : mixed;
}
