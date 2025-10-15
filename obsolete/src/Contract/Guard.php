<?php

namespace Wisp\Contract;

use Wisp\Http\Request;

interface Guard
{
   public function check (Request $request) : bool;
   public function is (Request $request, array $roles) : bool;
   public function can (Request $request, array $permissions) : bool;
}