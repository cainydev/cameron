<?php

use App\Actions\Agent\InterruptTaskAction;
use App\Enums\AgentTaskStatus;
use App\Jobs\RunTaskWorkerStep;
use App\Models\AgentTask;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
});

it('does not change task status', function () {
    $task = AgentTask::factory()->running()->create();

    (new InterruptTaskAction($task, 'Please reconsider your approach.'))->handle();

    expect($task->fresh()->status)->toBe(AgentTaskStatus::Running);
});

it('dispatches RunTaskWorkerStep with the human message as injectMessage', function () {
    $task = AgentTask::factory()->running()->create();

    (new InterruptTaskAction($task, 'Focus on the highest-spend campaign first.'))->handle();

    Queue::assertPushed(RunTaskWorkerStep::class, function ($job) use ($task) {
        return $job->task->id === $task->id
            && $job->injectMessage === 'Focus on the highest-spend campaign first.';
    });
});
