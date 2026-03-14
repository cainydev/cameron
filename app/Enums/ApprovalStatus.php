<?php

namespace App\Enums;

enum ApprovalStatus: string
{
    case Waiting = 'waiting';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Stale = 'stale';
}
