<?php

use App\Ai\Agents\GoalArchitect;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;

it('has instructions mentioning Goal Architect', function () {
    $agent = new GoalArchitect;

    expect((string) $agent->instructions())->toContain('Goal Architect');
});

it('defines the correct structured output schema keys', function () {
    $agent = new GoalArchitect;

    $schema = $agent->schema(new JsonSchemaTypeFactory);

    expect(array_keys($schema))->toBe([
        'sensor_tool_class',
        'sensor_arguments',
        'conditions',
        'is_one_off',
        'expires_at',
    ]);
});

it('can be prompted and returns structured output via fake', function () {
    GoalArchitect::fake();

    $response = (new GoalArchitect)->prompt(
        'I want ROAS to stay above 3.0 on my Google Ads account 12345.'
    );

    GoalArchitect::assertPrompted(fn ($prompt) => $prompt->contains('ROAS'));

    expect($response['sensor_tool_class'])->toBeString();
});
