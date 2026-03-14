<?php

use App\Ai\Tools\MarkTaskAsResolved;
use App\Enums\AgentTaskStatus;
use App\Models\AgentTask;
use App\Models\ResourceLock;
use Laravel\Ai\Tools\Request;

it('marks a task as completed and records the summary', function () {
    $task = AgentTask::factory()->running()->create([
        'context_payload' => ['sensor_data' => ['roas' => 1.2]],
    ]);

    $tool = (new MarkTaskAsResolved)->forTask($task->id);

    $result = $tool->execute([
        'task_id' => $task->id,
        'summary' => 'Paused underperforming campaign 456.',
    ]);

    $task->refresh();

    expect($result['success'])->toBeTrue()
        ->and($result['status'])->toBe('resolved')
        ->and($task->status)->toBe(AgentTaskStatus::Completed)
        ->and($task->context_payload)->toHaveKey('resolution_summary')
        ->and($task->context_payload['resolution_summary'])->toBe('Paused underperforming campaign 456.');
});

it('releases the resource lock when resolving', function () {
    $task = AgentTask::factory()->running()->create();
    ResourceLock::factory()->create(['task_id' => $task->id]);

    expect(ResourceLock::query()->where('task_id', $task->id)->exists())->toBeTrue();

    (new MarkTaskAsResolved)->execute([
        'task_id' => $task->id,
        'summary' => 'Fixed the issue.',
    ]);

    expect(ResourceLock::query()->where('task_id', $task->id)->exists())->toBeFalse();
});

it('returns failure when no task context is available', function () {
    $tool = new MarkTaskAsResolved;

    $result = $tool->execute(['summary' => 'Something']);

    expect($result['success'])->toBeFalse()
        ->and($result['status'])->toBe('no_task_context');
});

it('returns failure when task is not found', function () {
    $result = (new MarkTaskAsResolved)->execute([
        'task_id' => 99999,
        'summary' => 'Something',
    ]);

    expect($result['success'])->toBeFalse()
        ->and($result['status'])->toBe('task_not_found');
});

it('works through the handle method via the AI SDK interface', function () {
    $task = AgentTask::factory()->running()->create();

    $tool = (new MarkTaskAsResolved)->forTask($task->id);

    $response = $tool->handle(new Request([
        'task_id' => $task->id,
        'summary' => 'All good now.',
    ]));

    $decoded = json_decode($response, true);

    expect($decoded['success'])->toBeTrue()
        ->and($decoded['status'])->toBe('resolved');
});
