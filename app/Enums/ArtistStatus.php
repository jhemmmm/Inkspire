<?php

namespace App\Enums;

enum ArtistStatus: string
{
    case Available = 'available';
    case OnBreak = 'on_break';
    case OffShift = 'off_shift';
}
