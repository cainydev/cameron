<?php

use App\Models\AgentTask;
use App\Models\ResourceLock;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;

it('belongs to a task', function () {
    $lock = ResourceLock::factory()->create();

    expect($lock->task)->toBeInstanceOf(AgentTask::class);
});

it('casts expires_at as datetime', function () {
    $lock = ResourceLock::factory()->create();

    expect($lock->expires_at)->toBeInstanceOf(CarbonImmutable::class);
});

it('determines if the lock has expired', function () {
    $activeLock = ResourceLock::factory()->create([
        'expires_at' => now()->addHour(),
    ]);

    $expiredLock = ResourceLock::factory()->expired()->create();

    expect($activeLock->isExpired())->toBeFalse()
        ->and($expiredLock->isExpired())->toBeTrue();
});

it('enforces unique resource_id', function () {
    ResourceLock::factory()->create(['resource_id' => 'GoogleAdCampaign:123']);

    expect(fn () => ResourceLock::factory()->create(['resource_id' => 'GoogleAdCampaign:123']))
        ->toThrow(UniqueConstraintViolationException::class);
});
