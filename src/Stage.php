<?php

namespace Wisp;

enum Stage: string
{
   case Development = 'dev';
   case Staging     = 'test';
   case Production  = 'prod';
}
