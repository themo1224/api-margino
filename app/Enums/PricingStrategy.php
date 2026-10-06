<?php

namespace App\Enums;

enum PricingStrategy: string
{
    case FloorPlusMargin = 'floor_plus_margin';
    case MatchCheapest = 'match_cheapest';
    case UndercutPercent = 'undercut_percent';
    case Median = 'median';
}
