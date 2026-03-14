<?php

use App\Ai\Agents\GoalArchitect;
use App\Models\Shop;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Contracts\HasTools;

it('has instructions mentioning Goal Architect', function () {
    $agent = new GoalArchitect;

    expect((string) $agent->instructions())->toContain('Goal Architect');
});

it('instructs the LLM not to include IDs in sensor_arguments', function () {
    $agent = new GoalArchitect;

    expect((string) $agent->instructions())->toContain('do NOT include propertyId, customerId, or siteUrl');
});

it('defines the correct structured output schema keys', function () {
    $agent = new GoalArchitect;

    $schema = $agent->schema(new JsonSchemaTypeFactory);

    expect(array_keys($schema))->toBe([
        'name',
        'sensor_tool_class',
        'sensor_arguments',
        'conditions',
        'is_one_off',
        'expires_at',
        'initial_context',
    ]);
});

it('has no tools', function () {
    expect(new GoalArchitect)->not->toBeInstanceOf(HasTools::class);
});

it('can be constructed without a shop', function () {
    $agent = new GoalArchitect;

    expect($agent->shop)->toBeNull();
});

it('can be constructed with a shop', function () {
    $shop = Shop::factory()->make();
    $agent = new GoalArchitect($shop);

    expect($agent->shop)->toBe($shop);
});

it('can be prompted and returns structured output via fake', function () {
    GoalArchitect::fake();

    $response = (new GoalArchitect)->prompt(
        'I want ROAS to stay above 3.0 on my Google Ads account 12345.'
    );

    GoalArchitect::assertPrompted(fn ($prompt) => $prompt->contains('ROAS'));

    expect($response['sensor_tool_class'])->toBeString();
});
