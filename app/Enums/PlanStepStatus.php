<?php

declare(strict_types=1);

namespace App\Enums;

enum PlanStepStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case WaitingApproval = 'waiting_approval';
    case Completed = 'completed';
    case Failed = 'failed';
    case Skipped = 'skipped';
}
