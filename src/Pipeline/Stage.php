<?php

namespace Wisp\Pipeline;

enum Stage : string
{
   case before = 'before';
   case after  = 'after';
}