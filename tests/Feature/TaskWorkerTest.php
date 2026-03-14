<?php

use App\Ai\Agents\TaskWorker;
use App\Ai\Tools\AddGoalMemory;
use App\Ai\Tools\GetUnderperformingSearchTerms;
use App\Ai\Tools\MarkTaskAsResolved;
use App\Ai\Tools\PauseGoogleAdCampaign;
use App\Ai\Tools\UpdateAdsCampaignStatus;
use App\Ai\Tools\UpdateKeywordBid;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasStructuredOutput;

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

it('provides all six tools', function () {
    $worker = new TaskWorker(goalContext: '{}', taskId: 1);
    $tools = iterator_to_array($worker->tools());

    expect($tools)->toHaveCount(6)
        ->and($tools[0])->toBeInstanceOf(PauseGoogleAdCampaign::class)
        ->and($tools[1])->toBeInstanceOf(UpdateAdsCampaignStatus::class)
        ->and($tools[2])->toBeInstanceOf(AddGoalMemory::class)
        ->and($tools[3])->toBeInstanceOf(MarkTaskAsResolved::class)
        ->and($tools[4])->toBeInstanceOf(GetUnderperformingSearchTerms::class)
        ->and($tools[5])->toBeInstanceOf(UpdateKeywordBid::class);
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
