<?php

use App\Ai\Agents\CameronChat;
use App\Ai\Tools\CreateGoalFromDescription;
use App\Ai\Tools\GetAccountPerformanceSummary;
use App\Ai\Tools\GetActiveGoalsSummary;
use App\Ai\Tools\GetGa4Conversions;
use App\Ai\Tools\GetGa4Traffic;
use App\Ai\Tools\GetGoogleAdsCampaigns;
use App\Ai\Tools\GetPendingApprovals;
use App\Ai\Tools\GetSearchConsoleKeywords;
use App\Ai\Tools\UpdateAdsCampaignStatus;

it('has correct instructions mentioning Cameron', function () {
    $agent = new CameronChat;

    expect((string) $agent->instructions())->toContain('Cameron');
});

it('mentions CreateGoalFromDescription in its instructions', function () {
    $agent = new CameronChat;

    expect((string) $agent->instructions())->toContain('CreateGoalFromDescription');
});

it('does not include UpdateAdsCampaignStatus in its tools', function () {
    $agent = new CameronChat;
    $tools = iterator_to_array($agent->tools());

    $toolClasses = array_map(fn ($t) => get_class($t), $tools);

    expect($toolClasses)->not->toContain(UpdateAdsCampaignStatus::class);
});

it('provides all eight tools', function () {
    $agent = new CameronChat;
    $tools = iterator_to_array($agent->tools());

    expect($tools)->toHaveCount(8)
        ->and($tools[0])->toBeInstanceOf(GetPendingApprovals::class)
        ->and($tools[1])->toBeInstanceOf(GetActiveGoalsSummary::class)
        ->and($tools[2])->toBeInstanceOf(CreateGoalFromDescription::class)
        ->and($tools[3])->toBeInstanceOf(GetGa4Traffic::class)
        ->and($tools[4])->toBeInstanceOf(GetGoogleAdsCampaigns::class)
        ->and($tools[5])->toBeInstanceOf(GetSearchConsoleKeywords::class)
        ->and($tools[6])->toBeInstanceOf(GetGa4Conversions::class)
        ->and($tools[7])->toBeInstanceOf(GetAccountPerformanceSummary::class);
});

it('can be prompted via the AI SDK fake', function () {
    CameronChat::fake(['Hello! I am Cameron, your e-commerce assistant.']);

    $response = (new CameronChat)->prompt('Hello');

    expect((string) $response)->toContain('Cameron');

    CameronChat::assertPrompted('Hello');
});
