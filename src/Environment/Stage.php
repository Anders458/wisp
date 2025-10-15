<?php

namespace Wisp\Environment;

enum Stage : string
{
    case development = 'dev';
    case staging     = 'test';
    case production  = 'prod';
}