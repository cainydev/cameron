<?php

use App\Ai\Agents\TaskWorker;
use App\Ai\Tools\MarkTaskAsResolved;
use App\Models\Shop;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Contracts\HasTools;

it('has instructions referencing autonomous background worker', function () {
    $worker = new TaskWorker(goalContext: '{}', taskId: 1);

    expect((string) $worker->instructions())->toContain('autonomous background worker');
});

it('includes the task ID in its instructions', function () {
    $worker = new TaskWorker(goalContext: '{}', taskId: 42);

    expect((string) $worker->instructions())->toContain('42');
});

it('appends urgency to instructions when deadline is provided', function () {
    $worker = new TaskWorker(
        goalContext: '{}',
        taskId: 1,
        urgencyDeadline: '3 days and 4 hours remaining',
    );

    expect((string) $worker->instructions())
        ->toContain('URGENT')
        ->toContain('3 days and 4 hours remaining');
});

it('does not append urgency when no deadline is provided', function () {
    $worker = new TaskWorker(goalContext: '{}', taskId: 1);

    expect((string) $worker->instructions())->not->toContain('URGENT');
});

it('appends initial context section when provided', function () {
    $worker = new TaskWorker(goalContext: '{}', taskId: 1, initialContext: 'ROAS dropped to 1.2 yesterday.');

    expect((string) $worker->instructions())
        ->toContain('Goal Context from Strategist')
        ->toContain('ROAS dropped to 1.2 yesterday.');
});

it('does not append initial context section when null', function () {
    $worker = new TaskWorker(goalContext: '{}', taskId: 1);

    expect((string) $worker->instructions())->not->toContain('Goal Context from Strategist');
});

it('appends shared memory section when active memories are provided', function () {
    $worker = new TaskWorker(goalContext: '{}', taskId: 1, activeMemories: ['Campaign X is a top spender.', 'Brand terms convert well.']);

    expect((string) $worker->instructions())
        ->toContain('Shared Memory from Previous Workers')
        ->toContain('Campaign X is a top spender.')
        ->toContain('Brand terms convert well.');
});

it('does not append shared memory section when no memories', function () {
    $worker = new TaskWorker(goalContext: '{}', taskId: 1);

    expect((string) $worker->instructions())->not->toContain('Shared Memory from Previous Workers');
});

it('has tools', function () {
    expect(new TaskWorker(goalContext: '{}', taskId: 1))->toBeInstanceOf(HasTools::class);
});

it('always includes MarkTaskAsResolved', function () {
    $tools = iterator_to_array((new TaskWorker(goalContext: '{}', taskId: 1))->tools());

    expect(array_map(fn ($t) => $t::class, $tools))->toContain(MarkTaskAsResolved::class);
});

it('has more tools when a shop is given', function () {
    $withoutShop = iterator_to_array((new TaskWorker(goalContext: '{}', taskId: 1))->tools());
    $withShop = iterator_to_array((new TaskWorker(goalContext: '{}', taskId: 1, shop: Shop::factory()->make()))->tools());

    expect(count($withShop))->toBeGreaterThan(count($withoutShop));
});

it('has at least one write tool when a shop is given', function () {
    $tools = iterator_to_array((new TaskWorker(goalContext: '{}', taskId: 1, shop: Shop::factory()->make()))->tools());

    $hasWriteTool = false;

    foreach ($tools as $tool) {
        $prop = (new ReflectionClass($tool))->getProperty('isReadOnly');
        $prop->setAccessible(true);

        if (! $prop->getValue($tool)) {
            $hasWriteTool = true;
            break;
        }
    }

    expect($hasWriteTool)->toBeTrue();
});

it('implements Conversational', function () {
    $worker = new TaskWorker(goalContext: '{}', taskId: 1);

    expect($worker)->toBeInstanceOf(Conversational::class);
});

it('does not implement HasStructuredOutput', function () {
    $worker = new TaskWorker(goalContext: '{}', taskId: 1);

    expect($worker)->not->toBeInstanceOf(HasStructuredOutput::class);
});

it('can be prompted via the AI SDK fake', function () {
    TaskWorker::fake();

    $worker = new TaskWorker(goalContext: '{"roas": 1.2}', taskId: 1);

    $worker->prompt('Fix this failing goal.');

    TaskWorker::assertPrompted(fn ($prompt) => $prompt->contains('Fix this'));
});
