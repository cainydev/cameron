<?php

use App\Actions\Agent\ApproveTaskAction;
use App\Ai\Tools\PauseGoogleAdCampaign;
use App\Enums\AgentTaskStatus;
use App\Enums\ApprovalStatus;
use App\Jobs\RunTaskWorkerStep;
use App\Models\PendingApproval;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\mock;

beforeEach(function () {
    Queue::fake();
});

it('marks approval as approved', function () {
    $approval = PendingApproval::factory()->create([
        'tool_class' => PauseGoogleAdCampaign::class,
        'payload' => ['campaign_id' => 123, 'reason' => 'Low ROAS'],
    ]);

    mock(PauseGoogleAdCampaign::class)
        ->shouldReceive('execute')
        ->once()
        ->with($approval->payload);

    (new ApproveTaskAction($approval))->handle();

    expect($approval->fresh()->status)->toBe(ApprovalStatus::Approved);
});

it('calls execute on the tool class with stored payload', function () {
    $approval = PendingApproval::factory()->create([
        'tool_class' => PauseGoogleAdCampaign::class,
        'payload' => ['campaign_id' => 456, 'reason' => 'Test'],
    ]);

    $toolMock = mock(PauseGoogleAdCampaign::class)
        ->shouldReceive('execute')
        ->once()
        ->with(['campaign_id' => 456, 'reason' => 'Test'])
        ->getMock();

    app()->instance(PauseGoogleAdCampaign::class, $toolMock);

    (new ApproveTaskAction($approval))->handle();
});

it('sets task status to running', function () {
    $approval = PendingApproval::factory()->create([
        'tool_class' => PauseGoogleAdCampaign::class,
    ]);

    mock(PauseGoogleAdCampaign::class)->shouldReceive('execute')->once();

    (new ApproveTaskAction($approval))->handle();

    expect($approval->task->fresh()->status)->toBe(AgentTaskStatus::Running);
});

it('dispatches RunTaskWorkerStep with inject message containing APPROVED', function () {
    $approval = PendingApproval::factory()->create([
        'tool_class' => PauseGoogleAdCampaign::class,
    ]);

    mock(PauseGoogleAdCampaign::class)->shouldReceive('execute')->once();

    (new ApproveTaskAction($approval))->handle();

    Queue::assertPushed(RunTaskWorkerStep::class, function ($job) use ($approval) {
        return $job->task->id === $approval->task_id
            && str_contains($job->injectMessage, 'APPROVED');
    });
});

it('includes humanMessage in inject message when provided', function () {
    $approval = PendingApproval::factory()->create([
        'tool_class' => PauseGoogleAdCampaign::class,
    ]);

    mock(PauseGoogleAdCampaign::class)->shouldReceive('execute')->once();

    (new ApproveTaskAction($approval, humanMessage: 'Please proceed carefully.'))->handle();

    Queue::assertPushed(RunTaskWorkerStep::class, function ($job) {
        return str_contains($job->injectMessage, 'Please proceed carefully.');
    });
});
