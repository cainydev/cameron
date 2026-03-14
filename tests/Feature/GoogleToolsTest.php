<?php

use App\Ai\Tools\AddGoalMemory;
use App\Ai\Tools\GetAccountPerformanceSummary;
use App\Ai\Tools\GetGa4Conversions;
use App\Ai\Tools\GetGa4Traffic;
use App\Ai\Tools\GetGoogleAdsCampaigns;
use App\Ai\Tools\GetSearchConsoleKeywords;
use App\Ai\Tools\GetUnderperformingSearchTerms;
use App\Ai\Tools\UpdateAdsCampaignStatus;
use App\Ai\Tools\UpdateKeywordBid;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;

it('GetGa4Traffic is read-only', function () {
    $prop = (new ReflectionClass(GetGa4Traffic::class))->getProperty('isReadOnly');
    $prop->setAccessible(true);

    expect($prop->getValue(new GetGa4Traffic))->toBeTrue();
});

it('GetGa4Traffic description mentions GA4', function () {
    expect((string) (new GetGa4Traffic)->description())->toContain('GA4');
});

it('GetGa4Traffic schema contains required fields', function () {
    $schema = (new GetGa4Traffic)->schema(new JsonSchemaTypeFactory);

    expect($schema)->toHaveKey('propertyId')
        ->toHaveKey('startDate')
        ->toHaveKey('endDate');
});

it('GetGoogleAdsCampaigns is read-only', function () {
    $prop = (new ReflectionClass(GetGoogleAdsCampaigns::class))->getProperty('isReadOnly');
    $prop->setAccessible(true);

    expect($prop->getValue(new GetGoogleAdsCampaigns))->toBeTrue();
});

it('GetGoogleAdsCampaigns description mentions Google Ads', function () {
    expect((string) (new GetGoogleAdsCampaigns)->description())->toContain('Google Ads');
});

it('GetGoogleAdsCampaigns schema contains customerId and limit', function () {
    $schema = (new GetGoogleAdsCampaigns)->schema(new JsonSchemaTypeFactory);

    expect($schema)->toHaveKey('customerId')->toHaveKey('limit');
});

it('GetSearchConsoleKeywords is read-only', function () {
    $prop = (new ReflectionClass(GetSearchConsoleKeywords::class))->getProperty('isReadOnly');
    $prop->setAccessible(true);

    expect($prop->getValue(new GetSearchConsoleKeywords))->toBeTrue();
});

it('GetSearchConsoleKeywords description mentions Search Console', function () {
    expect((string) (new GetSearchConsoleKeywords)->description())->toContain('Search Console');
});

it('GetSearchConsoleKeywords schema contains required fields', function () {
    $schema = (new GetSearchConsoleKeywords)->schema(new JsonSchemaTypeFactory);

    expect($schema)
        ->toHaveKey('siteUrl')
        ->toHaveKey('startDate')
        ->toHaveKey('endDate')
        ->toHaveKey('limit');
});

it('UpdateAdsCampaignStatus requires approval', function () {
    $prop = (new ReflectionClass(UpdateAdsCampaignStatus::class))->getProperty('requiresApproval');
    $prop->setAccessible(true);

    expect($prop->getValue(new UpdateAdsCampaignStatus))->toBeTrue();
});

it('UpdateAdsCampaignStatus description mentions approval', function () {
    expect((string) (new UpdateAdsCampaignStatus)->description())->toContain('approval');
});

it('UpdateAdsCampaignStatus schema contains required fields', function () {
    $schema = (new UpdateAdsCampaignStatus)->schema(new JsonSchemaTypeFactory);

    expect($schema)
        ->toHaveKey('customerId')
        ->toHaveKey('campaignId')
        ->toHaveKey('status')
        ->toHaveKey('reason');
});

it('GetGa4Conversions is read-only', function () {
    $prop = (new ReflectionClass(GetGa4Conversions::class))->getProperty('isReadOnly');
    $prop->setAccessible(true);

    expect($prop->getValue(new GetGa4Conversions))->toBeTrue();
});

it('GetGa4Conversions description mentions GA4', function () {
    expect((string) (new GetGa4Conversions)->description())->toContain('GA4');
});

it('GetGa4Conversions schema contains all fields including optional eventName', function () {
    $schema = (new GetGa4Conversions)->schema(new JsonSchemaTypeFactory);

    expect($schema)
        ->toHaveKey('propertyId')
        ->toHaveKey('startDate')
        ->toHaveKey('endDate')
        ->toHaveKey('eventName');
});

it('GetAccountPerformanceSummary is read-only', function () {
    $prop = (new ReflectionClass(GetAccountPerformanceSummary::class))->getProperty('isReadOnly');
    $prop->setAccessible(true);

    expect($prop->getValue(new GetAccountPerformanceSummary))->toBeTrue();
});

it('GetAccountPerformanceSummary description mentions briefing', function () {
    expect((string) (new GetAccountPerformanceSummary)->description())->toContain('briefing');
});

it('GetAccountPerformanceSummary schema contains all required fields', function () {
    $schema = (new GetAccountPerformanceSummary)->schema(new JsonSchemaTypeFactory);

    expect($schema)
        ->toHaveKey('propertyId')
        ->toHaveKey('customerId')
        ->toHaveKey('startDate')
        ->toHaveKey('endDate');
});

it('GetUnderperformingSearchTerms is read-only', function () {
    $prop = (new ReflectionClass(GetUnderperformingSearchTerms::class))->getProperty('isReadOnly');
    $prop->setAccessible(true);

    expect($prop->getValue(new GetUnderperformingSearchTerms))->toBeTrue();
});

it('GetUnderperformingSearchTerms description mentions wasted spend', function () {
    expect((string) (new GetUnderperformingSearchTerms)->description())->toContain('wasted spend');
});

it('GetUnderperformingSearchTerms schema contains customerId', function () {
    $schema = (new GetUnderperformingSearchTerms)->schema(new JsonSchemaTypeFactory);

    expect($schema)->toHaveKey('customerId');
});

it('UpdateKeywordBid requires approval', function () {
    $prop = (new ReflectionClass(UpdateKeywordBid::class))->getProperty('requiresApproval');
    $prop->setAccessible(true);

    expect($prop->getValue(new UpdateKeywordBid))->toBeTrue();
});

it('UpdateKeywordBid description mentions approval', function () {
    expect((string) (new UpdateKeywordBid)->description())->toContain('approval');
});

it('UpdateKeywordBid schema contains all required fields', function () {
    $schema = (new UpdateKeywordBid)->schema(new JsonSchemaTypeFactory);

    expect($schema)
        ->toHaveKey('customerId')
        ->toHaveKey('adGroupId')
        ->toHaveKey('criterionId')
        ->toHaveKey('newCpcBidMicros')
        ->toHaveKey('reason');
});

it('AddGoalMemory does not require approval', function () {
    $prop = (new ReflectionClass(AddGoalMemory::class))->getProperty('requiresApproval');
    $prop->setAccessible(true);

    expect($prop->getValue(new AddGoalMemory))->toBeFalse();
});

it('AddGoalMemory description mentions insight', function () {
    expect((string) (new AddGoalMemory)->description())->toContain('insight');
});

it('AddGoalMemory schema contains all required fields', function () {
    $schema = (new AddGoalMemory)->schema(new JsonSchemaTypeFactory);

    expect($schema)
        ->toHaveKey('goal_id')
        ->toHaveKey('insight')
        ->toHaveKey('valid_for_hours');
});
