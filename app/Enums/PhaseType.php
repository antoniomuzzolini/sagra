<?php

namespace App\Enums;

enum PhaseType: string
{
    case Preparation = 'preparation';
    case Service = 'service';
    case Teardown = 'teardown';
}
