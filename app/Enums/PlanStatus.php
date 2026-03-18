<?php

declare(strict_types=1);

namespace App\Enums;

enum PlanStatus: string
{
    case Planning = 'planning';
    case Executing = 'executing';
    case WaitingApproval = 'waiting_approval';
    case Completed = 'completed';
    case Failed = 'failed';
    case Aborted = 'aborted';
}
