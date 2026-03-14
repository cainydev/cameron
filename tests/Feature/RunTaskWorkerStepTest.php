<?php

use App\Ai\Agents\TaskWorker;
use App\Enums\AgentTaskStatus;
use App\Jobs\RunTaskWorkerStep;
use App\Models\AgentTask;
use App\Models\ResourceLock;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    TaskWorker::fake();
});

it('skips aborted tasks without prompting or re-dispatching', function () {
    $task = AgentTask::factory()->aborted()->create();

    (new RunTaskWorkerStep($task))->handle();

    TaskWorker::assertNeverPrompted();
    Queue::assertNothingPushed();
});

it('skips completed tasks without prompting or re-dispatching', function () {
    $task = AgentTask::factory()->completed()->create();

    (new RunTaskWorkerStep($task))->handle();

    TaskWorker::assertNeverPrompted();
    Queue::assertNothingPushed();
});

it('skips failed tasks without prompting or re-dispatching', function () {
    $task = AgentTask::factory()->failed()->create();

    (new RunTaskWorkerStep($task))->handle();

    TaskWorker::assertNeverPrompted();
    Queue::assertNothingPushed();
});

it('skips waiting_approval tasks without prompting or re-dispatching', function () {
    $task = AgentTask::factory()->waitingApproval()->create();

    (new RunTaskWorkerStep($task))->handle();

    TaskWorker::assertNeverPrompted();
    Queue::assertNothingPushed();
});

it('sets status to running before prompting', function () {
    $task = AgentTask::factory()->create();

    (new RunTaskWorkerStep($task))->handle();

    expect($task->fresh()->status)->toBe(AgentTaskStatus::Running);
});

it('re-dispatches self when task is still running after step', function () {
    $task = AgentTask::factory()->create();

    (new RunTaskWorkerStep($task, stepCount: 2))->handle();

    Queue::assertPushed(RunTaskWorkerStep::class, function ($job) use ($task) {
        return $job->task->id === $task->id
            && $job->stepCount === 3
            && $job->injectMessage === null;
    });
});

it('does not re-dispatch when task is completed after step', function () {
    $task = AgentTask::factory()->create();

    TaskWorker::fake(function () use ($task) {
        $task->update(['status' => AgentTaskStatus::Completed]);
    });

    (new RunTaskWorkerStep($task))->handle();

    Queue::assertNotPushed(RunTaskWorkerStep::class);
});

it('does not re-dispatch when task is waiting_approval after step', function () {
    $task = AgentTask::factory()->create();

    TaskWorker::fake(function () use ($task) {
        $task->update(['status' => AgentTaskStatus::WaitingApproval]);
    });

    (new RunTaskWorkerStep($task))->handle();

    Queue::assertNotPushed(RunTaskWorkerStep::class);
});

it('uses injectMessage as prompt text when provided', function () {
    $task = AgentTask::factory()->create();

    (new RunTaskWorkerStep($task, injectMessage: 'Custom operator message'))->handle();

    TaskWorker::assertPrompted(fn ($prompt) => $prompt->contains('Custom operator message'));
});

it('uses default system prompt when no injectMessage provided', function () {
    $task = AgentTask::factory()->create();

    (new RunTaskWorkerStep($task))->handle();

    TaskWorker::assertPrompted(fn ($prompt) => $prompt->contains('[SYSTEM] Next step.'));
});

it('hard-aborts to failed and releases lock at stepCount >= 30', function () {
    $task = AgentTask::factory()->create();
    ResourceLock::factory()->create(['task_id' => $task->id]);

    (new RunTaskWorkerStep($task, stepCount: 30))->handle();

    expect($task->fresh()->status)->toBe(AgentTaskStatus::Failed);
    expect(ResourceLock::query()->where('task_id', $task->id)->count())->toBe(0);
    TaskWorker::assertNeverPrompted();
    Queue::assertNothingPushed();
});

it('failed() method transitions task to failed and deletes resource lock', function () {
    $task = AgentTask::factory()->running()->create();
    ResourceLock::factory()->create(['task_id' => $task->id]);

    $job = new RunTaskWorkerStep($task);
    $job->failed(new RuntimeException('API timeout'));

    expect($task->fresh()->status)->toBe(AgentTaskStatus::Failed);
    expect(ResourceLock::query()->where('task_id', $task->id)->count())->toBe(0);
});

it('failed() does not override completed status', function () {
    $task = AgentTask::factory()->completed()->create();

    $job = new RunTaskWorkerStep($task);
    $job->failed(new RuntimeException('Some error'));

    expect($task->fresh()->status)->toBe(AgentTaskStatus::Completed);
});

it('failed() does not override aborted status', function () {
    $task = AgentTask::factory()->aborted()->create();

    $job = new RunTaskWorkerStep($task);
    $job->failed(new RuntimeException('Some error'));

    expect($task->fresh()->status)->toBe(AgentTaskStatus::Aborted);
});
