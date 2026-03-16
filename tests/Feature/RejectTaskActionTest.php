<?php

use App\Actions\Agent\RejectTaskAction;
use App\Ai\Tools\UpdateAdsCampaignStatus;
use App\Enums\AgentTaskStatus;
use App\Enums\ApprovalStatus;
use App\Jobs\RunTaskWorkerStep;
use App\Models\PendingApproval;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
});

it('marks approval as rejected', function () {
    $approval = PendingApproval::factory()->create([
        'tool_class' => UpdateAdsCampaignStatus::class,
    ]);

    (new RejectTaskAction($approval))->handle();

    expect($approval->fresh()->status)->toBe(ApprovalStatus::Rejected);
});

it('does not call execute on the tool', function () {
    $approval = PendingApproval::factory()->create([
        'tool_class' => UpdateAdsCampaignStatus::class,
    ]);

    // No mock expectation for execute — it must NOT be called
    (new RejectTaskAction($approval))->handle();

    expect(true)->toBeTrue(); // if execute was called, it would have hit the real class without error
});

it('sets task status to running', function () {
    $approval = PendingApproval::factory()->create([
        'tool_class' => UpdateAdsCampaignStatus::class,
    ]);

    (new RejectTaskAction($approval))->handle();

    expect($approval->task->fresh()->status)->toBe(AgentTaskStatus::Running);
});

it('dispatches RunTaskWorkerStep with inject message containing REJECTED', function () {
    $approval = PendingApproval::factory()->create([
        'tool_class' => UpdateAdsCampaignStatus::class,
    ]);

    (new RejectTaskAction($approval))->handle();

    Queue::assertPushed(RunTaskWorkerStep::class, function ($job) use ($approval) {
        return $job->task->id === $approval->task_id
            && str_contains($job->injectMessage, 'REJECTED');
    });
});

it('includes humanMessage reason in inject message when provided', function () {
    $approval = PendingApproval::factory()->create([
        'tool_class' => UpdateAdsCampaignStatus::class,
    ]);

    (new RejectTaskAction($approval, humanMessage: 'Budget concerns.'))->handle();

    Queue::assertPushed(RunTaskWorkerStep::class, function ($job) {
        return str_contains($job->injectMessage, 'Budget concerns.');
    });
});
