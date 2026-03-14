<?php

use App\Ai\Tools\AbstractAgentTool;
use App\Enums\ApprovalStatus;
use App\Models\AgentTask;
use App\Models\PendingApproval;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    $this->readOnlyTool = new class extends AbstractAgentTool
    {
        protected bool $requiresApproval = false;

        protected bool $isReadOnly = true;

        public function description(): string
        {
            return 'A read-only test tool.';
        }

        public function execute(array $arguments): mixed
        {
            return ['metric' => $arguments['metric'] ?? 'unknown', 'value' => 42];
        }

        public function schema(JsonSchema $schema): array
        {
            return [
                'metric' => $schema->string()->required(),
            ];
        }
    };

    $this->approvalTool = new class extends AbstractAgentTool
    {
        protected bool $requiresApproval = true;

        public function description(): string
        {
            return 'A tool requiring approval.';
        }

        public function execute(array $arguments): mixed
        {
            return ['success' => true];
        }

        public function schema(JsonSchema $schema): array
        {
            return [
                'reason' => $schema->string()->required(),
            ];
        }
    };
});

it('executes directly when approval is not required', function () {
    $request = new Request(['metric' => 'roas']);

    $result = $this->readOnlyTool->handle($request);

    $decoded = json_decode($result, true);

    expect($decoded)
        ->toHaveKey('metric', 'roas')
        ->toHaveKey('value', 42);
});

it('queues for approval when approval is required', function () {
    $task = AgentTask::factory()->create();

    $request = new Request(['reason' => 'ROAS dropped below 2.0']);

    $result = $this->approvalTool->forTask($task->id)->handle($request);

    expect($result)->toContain('Action queued for human approval');

    $approval = PendingApproval::query()->where('task_id', $task->id)->first();

    expect($approval)
        ->not->toBeNull()
        ->status->toBe(ApprovalStatus::Waiting)
        ->reasoning->toBe('ROAS dropped below 2.0');
});

it('updates task status to waiting_approval when queuing', function () {
    $task = AgentTask::factory()->running()->create();

    $request = new Request(['reason' => 'Budget exceeded']);

    $this->approvalTool->forTask($task->id)->handle($request);

    $task->refresh();

    expect($task->status->value)->toBe('waiting_approval');
});

it('sets the active task via forTask and returns the tool instance', function () {
    $result = $this->readOnlyTool->forTask(99);

    expect($result)->toBe($this->readOnlyTool);
});
