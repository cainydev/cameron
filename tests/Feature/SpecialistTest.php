<?php

use App\Ai\Agents\Specialist;
use App\Enums\AgentRole;
use App\Models\Shop;

it('includes role in instructions', function () {
    $shop = Shop::factory()->create();

    $specialist = new Specialist(
        role: AgentRole::Ads,
        shop: $shop,
        stepInstruction: 'Pause campaigns with ROAS below 1.0',
        taskId: 1,
    );

    $instructions = $specialist->instructions();

    expect($instructions)
        ->toContain('Ads Specialist')
        ->toContain('Pause campaigns with ROAS below 1.0');
});

it('injects working memory from prior steps', function () {
    $shop = Shop::factory()->create();

    $specialist = new Specialist(
        role: AgentRole::Ads,
        shop: $shop,
        stepInstruction: 'Take action based on prior findings',
        taskId: 1,
        workingMemory: [
            'step_1' => 'GA4 shows traffic dropped 30% in the last 7 days',
            'step_2' => 'Top 3 campaigns have negative ROAS',
        ],
    );

    $instructions = $specialist->instructions();

    expect($instructions)
        ->toContain('step_1')
        ->toContain('GA4 shows traffic dropped 30%')
        ->toContain('step_2');
});

it('injects urgency deadline when provided', function () {
    $shop = Shop::factory()->create();

    $specialist = new Specialist(
        role: AgentRole::Analytics,
        shop: $shop,
        stepInstruction: 'Fetch traffic data urgently',
        taskId: 1,
        urgencyDeadline: 'You have 2 hours remaining.',
    );

    $instructions = $specialist->instructions();

    expect($instructions)->toContain('URGENT')->toContain('2 hours remaining');
});

it('injects goal context when provided', function () {
    $shop = Shop::factory()->create();

    $specialist = new Specialist(
        role: AgentRole::Analytics,
        shop: $shop,
        stepInstruction: 'Analyze the data',
        taskId: 1,
        goalContext: '{"roas": 1.2, "spend": 500}',
    );

    $instructions = $specialist->instructions();

    expect($instructions)->toContain('"roas": 1.2');
});
