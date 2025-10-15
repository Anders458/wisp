<?php

namespace Wisp\Http;

use ArrayAccess;

interface Session extends ArrayAccess
{
   public function is (string ...$roles) : bool;
   public function can (string ...$permissions) : bool;
   public function toArray () : array;
}