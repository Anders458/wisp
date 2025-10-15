<?php

namespace Wisp;

use Wisp\Http\Request;
use Wisp\Http\Response;
use Wisp\Service\Flash;

abstract class Controller
{
   protected Request  $request;
   protected Response $response;
   protected Flash    $flash;
}