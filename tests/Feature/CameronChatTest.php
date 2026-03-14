<?php

use App\Ai\Agents\CameronChat;
use App\Ai\Tools\UpdateAdsCampaignStatus;
use App\Models\Shop;

it('has instructions that include the ads core guidelines', function () {
    $agent = new CameronChat(Shop::factory()->make());

    expect((string) $agent->instructions())->toContain('Non-Negotiable Operating Principles');
});

it('mentions CreateGoalFromDescription in its instructions', function () {
    $agent = new CameronChat(Shop::factory()->make());

    expect((string) $agent->instructions())->toContain('CreateGoalFromDescription');
});

it('has no tools that require approval', function () {
    $tools = iterator_to_array((new CameronChat(Shop::factory()->make()))->tools());

    foreach ($tools as $tool) {
        $prop = (new ReflectionClass($tool))->getProperty('requiresApproval');
        $prop->setAccessible(true);
        $class = $tool::class;

        expect($prop->getValue($tool))
            ->toBeFalse("Expected {$class} not to require approval");
    }
});

it('does not include UpdateAdsCampaignStatus in its tools', function () {
    $tools = iterator_to_array((new CameronChat(Shop::factory()->make()))->tools());

    expect(array_map(fn ($t) => $t::class, $tools))
        ->not->toContain(UpdateAdsCampaignStatus::class);
});

it('can be prompted via the AI SDK fake', function () {
    CameronChat::fake(['Hello! I am Cameron, your e-commerce assistant.']);

    $response = (new CameronChat(Shop::factory()->make()))->prompt('Hello');

    expect((string) $response)->toContain('Cameron');

    CameronChat::assertPrompted('Hello');
});
