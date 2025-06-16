<?php

namespace Wisp;

use Wisp\Http\Request;
use Wisp\Http\Response;

abstract class Controller
{
   protected Request  $request;
   protected Response $response;
}