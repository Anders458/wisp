<?php

namespace Wisp\Pipeline;

enum Hook : string
{
   case Before = 'before';
   case After  = 'after';
}
