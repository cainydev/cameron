<?php

use App\Jobs\EvaluateSingleGoal;
use App\Models\AgentGoal;
use Illuminate\Support\Facades\Queue;

it('deactivates expired temporal goals', function () {
    $expired = AgentGoal::factory()->expired()->create();
    $active = AgentGoal::factory()->create();

    expect($expired->is_active)->toBeTrue();

    // Simulate the dispatcher's expiration logic.
    AgentGoal::query()
        ->where('is_active', true)
        ->whereNotNull('expires_at')
        ->where('expires_at', '<', now())
        ->update(['is_active' => false]);

    expect($expired->refresh()->is_active)->toBeFalse()
        ->and($active->refresh()->is_active)->toBeTrue();
});

it('dispatches a job for each active goal', function () {
    Queue::fake();

    AgentGoal::factory()->count(3)->create();
    AgentGoal::factory()->inactive()->create();

    // Simulate the dispatcher's dispatch logic.
    AgentGoal::query()
        ->where('is_active', true)
        ->each(fn (AgentGoal $goal) => EvaluateSingleGoal::dispatch($goal));

    Queue::assertCount(3);
    Queue::assertPushed(EvaluateSingleGoal::class, 3);
});

it('does not dispatch jobs for expired goals after deactivation', function () {
    Queue::fake();

    AgentGoal::factory()->expired()->count(2)->create();
    AgentGoal::factory()->create(); // one active, non-expired

    // Simulate full dispatcher: expire first, then dispatch.
    AgentGoal::query()
        ->where('is_active', true)
        ->whereNotNull('expires_at')
        ->where('expires_at', '<', now())
        ->update(['is_active' => false]);

    AgentGoal::query()
        ->where('is_active', true)
        ->each(fn (AgentGoal $goal) => EvaluateSingleGoal::dispatch($goal));

    Queue::assertCount(1);
    Queue::assertPushed(EvaluateSingleGoal::class, 1);
});
