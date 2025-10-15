<?php

namespace Wisp\Pipeline;

enum Lifecycle : string
{
   case before = 'before';
   case after  = 'after';
}