<?php

namespace App\Enums;

enum RivalMatchStatus: string
{
    case AutoLinked = 'auto_linked';
    case NeedsConfirm = 'needs_confirm';
    case Rejected = 'rejected';
}
