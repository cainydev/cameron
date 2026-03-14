<?php

use App\Actions\Agent\AbortTaskAction;
use App\Enums\AgentTaskStatus;
use App\Jobs\RunTaskWorkerStep;
use App\Models\AgentTask;
use App\Models\ResourceLock;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
});

it('sets task status to aborted', function () {
    $task = AgentTask::factory()->running()->create();

    (new AbortTaskAction($task))->handle();

    expect($task->fresh()->status)->toBe(AgentTaskStatus::Aborted);
});

it('deletes resource lock for the task', function () {
    $task = AgentTask::factory()->running()->create();
    ResourceLock::factory()->create(['task_id' => $task->id]);

    (new AbortTaskAction($task))->handle();

    expect(ResourceLock::query()->where('task_id', $task->id)->count())->toBe(0);
});

it('does not dispatch RunTaskWorkerStep', function () {
    $task = AgentTask::factory()->running()->create();

    (new AbortTaskAction($task))->handle();

    Queue::assertNotPushed(RunTaskWorkerStep::class);
});
