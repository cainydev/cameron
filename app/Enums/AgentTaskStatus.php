<?php

declare(strict_types=1);

namespace App\Enums;

enum AgentTaskStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case WaitingApproval = 'waiting_approval';
    case Completed = 'completed';
    case Stale = 'stale';
    case Aborted = 'aborted';
    case Failed = 'failed';
}
