<?php

use App\Enums\AgentTaskStatus;
use App\Models\AgentGoal;
use App\Models\AgentTask;
use App\Models\PendingApproval;
use App\Models\ResourceLock;

it('belongs to a goal', function () {
    $task = AgentTask::factory()->create();

    expect($task->goal)->toBeInstanceOf(AgentGoal::class);
});

it('casts status to AgentTaskStatus enum', function () {
    $task = AgentTask::factory()->create(['status' => 'running']);

    expect($task->status)->toBe(AgentTaskStatus::Running);
});

it('casts context_payload as array', function () {
    $task = AgentTask::factory()->create([
        'context_payload' => ['key' => 'value'],
    ]);

    $task->refresh();

    expect($task->context_payload)->toBeArray()->toHaveKey('key');
});

it('has many pending approvals', function () {
    $task = AgentTask::factory()->create();

    PendingApproval::factory()->count(2)->create(['task_id' => $task->id]);

    expect($task->pendingApprovals)->toHaveCount(2);
});

it('has resource locks', function () {
    $task = AgentTask::factory()->create();

    ResourceLock::factory()->create(['task_id' => $task->id]);

    expect($task->resourceLocks->first())->toBeInstanceOf(ResourceLock::class);
});

it('uses factory states for status transitions', function () {
    $pending = AgentTask::factory()->create();
    $running = AgentTask::factory()->running()->create();
    $waiting = AgentTask::factory()->waitingApproval()->create();
    $completed = AgentTask::factory()->completed()->create();

    expect($pending->status)->toBe(AgentTaskStatus::Pending)
        ->and($running->status)->toBe(AgentTaskStatus::Running)
        ->and($waiting->status)->toBe(AgentTaskStatus::WaitingApproval)
        ->and($completed->status)->toBe(AgentTaskStatus::Completed);
});

it('factory aborted() state sets status to aborted', function () {
    $task = AgentTask::factory()->aborted()->create();

    expect($task->status)->toBe(AgentTaskStatus::Aborted);
});

it('factory failed() state sets status to failed', function () {
    $task = AgentTask::factory()->failed()->create();

    expect($task->status)->toBe(AgentTaskStatus::Failed);
});
