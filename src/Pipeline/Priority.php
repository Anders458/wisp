<?php

namespace Wisp\Pipeline;

enum Priority : int
{
   case Hook = 50;
   case Listener = 40;
   case Middleware = 30;
}