<?php

namespace App\Enums;

enum QueueStatus: string
{
    case Waiting = 'waiting';
    case Serving = 'serving';
    case Done = 'done';
}
