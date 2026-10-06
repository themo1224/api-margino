<?php

namespace App\Enums;

enum AlertType: string
{
    case BelowFloor = 'below_floor';
    case RivalUndercut = 'rival_undercut';
    case StaleCost = 'stale_cost';
}
