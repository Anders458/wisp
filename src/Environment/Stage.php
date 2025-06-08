<?php

namespace Wisp\Environment;

enum Stage: string
{
   case development = 'development';
   case staging     = 'staging';
   case production  = 'production';
}