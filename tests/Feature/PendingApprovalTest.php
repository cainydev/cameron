<?php

use App\Enums\ApprovalStatus;
use App\Models\AgentTask;
use App\Models\PendingApproval;
use Carbon\CarbonImmutable;

it('belongs to a task', function () {
    $approval = PendingApproval::factory()->create();

    expect($approval->task)->toBeInstanceOf(AgentTask::class);
});

it('casts status to ApprovalStatus enum', function () {
    $approval = PendingApproval::factory()->create();

    expect($approval->status)->toBe(ApprovalStatus::Waiting);
});

it('casts payload as array', function () {
    $approval = PendingApproval::factory()->create([
        'payload' => ['campaign_id' => 12345],
    ]);

    $approval->refresh();

    expect($approval->payload)->toBeArray()->toHaveKey('campaign_id');
});

it('casts expires_at as datetime', function () {
    $approval = PendingApproval::factory()->create([
        'expires_at' => '2026-12-31 23:59:59',
    ]);

    expect($approval->expires_at)->toBeInstanceOf(CarbonImmutable::class);
});

it('uses factory states for approval and rejection', function () {
    $approved = PendingApproval::factory()->approved()->create();
    $rejected = PendingApproval::factory()->rejected()->create();

    expect($approved->status)->toBe(ApprovalStatus::Approved)
        ->and($rejected->status)->toBe(ApprovalStatus::Rejected);
});
