<?php

use App\Ai\Tools\AddNegativeKeyword;
use App\Ai\Tools\GetAdGroupPerformance;
use App\Ai\Tools\GetAdsRecommendations;
use App\Ai\Tools\GetAuctionInsights;
use App\Ai\Tools\GetKeywordPerformance;
use App\Ai\Tools\GetMerchantProductIssues;
use App\Ai\Tools\GetMerchantProducts;
use App\Ai\Tools\GetNegativeKeywords;
use App\Ai\Tools\SuggestNegativeKeywords;
use App\Ai\Tools\UpdateCampaignBudget;
use App\Enums\ToolCategory;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;

it('GetAdGroupPerformance is read-only with GoogleAds category', function () {
    $tool = new GetAdGroupPerformance;

    expect($tool->category())->toBe(ToolCategory::GoogleAds);

    $prop = (new ReflectionClass($tool))->getProperty('isReadOnly');
    expect($prop->getValue($tool))->toBeTrue();
});

it('GetAdGroupPerformance schema has required date fields', function () {
    $schema = (new GetAdGroupPerformance)->schema(new JsonSchemaTypeFactory);

    expect($schema)
        ->toHaveKey('startDate')
        ->toHaveKey('endDate')
        ->toHaveKey('campaignId')
        ->toHaveKey('limit');
});

it('GetKeywordPerformance is read-only with GoogleAds category', function () {
    $tool = new GetKeywordPerformance;

    expect($tool->category())->toBe(ToolCategory::GoogleAds);

    $prop = (new ReflectionClass($tool))->getProperty('isReadOnly');
    expect($prop->getValue($tool))->toBeTrue();
});

it('GetKeywordPerformance schema has required date fields and optional filters', function () {
    $schema = (new GetKeywordPerformance)->schema(new JsonSchemaTypeFactory);

    expect($schema)
        ->toHaveKey('startDate')
        ->toHaveKey('endDate')
        ->toHaveKey('campaignId')
        ->toHaveKey('adGroupId')
        ->toHaveKey('limit');
});

it('GetNegativeKeywords is read-only with GoogleAds category', function () {
    $tool = new GetNegativeKeywords;

    expect($tool->category())->toBe(ToolCategory::GoogleAds);

    $prop = (new ReflectionClass($tool))->getProperty('isReadOnly');
    expect($prop->getValue($tool))->toBeTrue();
});

it('GetNegativeKeywords schema requires campaignId', function () {
    $schema = (new GetNegativeKeywords)->schema(new JsonSchemaTypeFactory);

    expect($schema)->toHaveKey('campaignId')->toHaveKey('limit');
});

it('GetAdsRecommendations is read-only with GoogleAds category', function () {
    $tool = new GetAdsRecommendations;

    expect($tool->category())->toBe(ToolCategory::GoogleAds);

    $prop = (new ReflectionClass($tool))->getProperty('isReadOnly');
    expect($prop->getValue($tool))->toBeTrue();
});

it('GetAdsRecommendations schema has optional types and limit', function () {
    $schema = (new GetAdsRecommendations)->schema(new JsonSchemaTypeFactory);

    expect($schema)->toHaveKey('types')->toHaveKey('limit');
});

it('AddNegativeKeyword requires approval with GoogleAds category', function () {
    $tool = new AddNegativeKeyword;

    expect($tool->category())->toBe(ToolCategory::GoogleAds);

    $prop = (new ReflectionClass($tool))->getProperty('requiresApproval');
    expect($prop->getValue($tool))->toBeTrue();
});

it('AddNegativeKeyword schema requires campaignId, keyword, matchType, reason', function () {
    $schema = (new AddNegativeKeyword)->schema(new JsonSchemaTypeFactory);

    expect($schema)
        ->toHaveKey('campaignId')
        ->toHaveKey('keyword')
        ->toHaveKey('matchType')
        ->toHaveKey('reason');
});

it('UpdateCampaignBudget requires approval with GoogleAds category', function () {
    $tool = new UpdateCampaignBudget;

    expect($tool->category())->toBe(ToolCategory::GoogleAds);

    $prop = (new ReflectionClass($tool))->getProperty('requiresApproval');
    expect($prop->getValue($tool))->toBeTrue();
});

it('UpdateCampaignBudget schema requires campaignId, budget, reason', function () {
    $schema = (new UpdateCampaignBudget)->schema(new JsonSchemaTypeFactory);

    expect($schema)
        ->toHaveKey('campaignId')
        ->toHaveKey('newDailyBudgetMicros')
        ->toHaveKey('reason');
});

it('UpdateCampaignBudget label includes dollar amount when arguments provided', function () {
    $tool = new UpdateCampaignBudget;

    $label = $tool->label(['campaignId' => '123', 'newDailyBudgetMicros' => 50_000_000]);

    expect($label)->toContain('$50.00');
});

it('GetAuctionInsights is read-only with GoogleAds category', function () {
    $tool = new GetAuctionInsights;

    expect($tool->category())->toBe(ToolCategory::GoogleAds);

    $prop = (new ReflectionClass($tool))->getProperty('isReadOnly');
    expect($prop->getValue($tool))->toBeTrue();
});

it('GetAuctionInsights schema has required date fields and optional filters', function () {
    $schema = (new GetAuctionInsights)->schema(new JsonSchemaTypeFactory);

    expect($schema)
        ->toHaveKey('startDate')
        ->toHaveKey('endDate')
        ->toHaveKey('campaignId')
        ->toHaveKey('limit');
});

it('GetAuctionInsights label returns expected string', function () {
    expect((new GetAuctionInsights)->label())->toBe('Auction Insights');
});

it('SuggestNegativeKeywords is read-only with GoogleAds category', function () {
    $tool = new SuggestNegativeKeywords;

    expect($tool->category())->toBe(ToolCategory::GoogleAds);

    $prop = (new ReflectionClass($tool))->getProperty('isReadOnly');
    expect($prop->getValue($tool))->toBeTrue();
});

it('SuggestNegativeKeywords schema requires campaignId', function () {
    $schema = (new SuggestNegativeKeywords)->schema(new JsonSchemaTypeFactory);

    expect($schema)
        ->toHaveKey('campaignId')
        ->toHaveKey('minCostMicros')
        ->toHaveKey('limit');
});

it('GetMerchantProducts is read-only with GoogleAds category', function () {
    $tool = new GetMerchantProducts;

    expect($tool->category())->toBe(ToolCategory::GoogleAds);

    $prop = (new ReflectionClass($tool))->getProperty('isReadOnly');
    expect($prop->getValue($tool))->toBeTrue();
});

it('GetMerchantProducts requires merchant_center_id shop field', function () {
    expect((new GetMerchantProducts)->requiredShopFields())->toBe(['merchant_center_id']);
});

it('GetMerchantProducts schema has optional pageSize and pageToken', function () {
    $schema = (new GetMerchantProducts)->schema(new JsonSchemaTypeFactory);

    expect($schema)->toHaveKey('pageSize')->toHaveKey('pageToken');
});

it('GetMerchantProductIssues is read-only with GoogleAds category', function () {
    $tool = new GetMerchantProductIssues;

    expect($tool->category())->toBe(ToolCategory::GoogleAds);

    $prop = (new ReflectionClass($tool))->getProperty('isReadOnly');
    expect($prop->getValue($tool))->toBeTrue();
});

it('GetMerchantProductIssues requires merchant_center_id shop field', function () {
    expect((new GetMerchantProductIssues)->requiredShopFields())->toBe(['merchant_center_id']);
});

it('GetMerchantProductIssues schema has optional pageSize and pageToken', function () {
    $schema = (new GetMerchantProductIssues)->schema(new JsonSchemaTypeFactory);

    expect($schema)->toHaveKey('pageSize')->toHaveKey('pageToken');
});
