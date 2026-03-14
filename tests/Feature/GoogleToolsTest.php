<?php

use App\Ai\Tools\AddGoalMemory;
use App\Ai\Tools\CheckWebsiteStatus;
use App\Ai\Tools\GetAccountPerformanceSummary;
use App\Ai\Tools\GetGa4Conversions;
use App\Ai\Tools\GetGa4ECommerceItemSales;
use App\Ai\Tools\GetGa4LandingPagePerformance;
use App\Ai\Tools\GetGa4Traffic;
use App\Ai\Tools\GetGa4TrafficSources;
use App\Ai\Tools\GetGoogleAdsCampaigns;
use App\Ai\Tools\GetPageHtmlContent;
use App\Ai\Tools\GetSearchConsoleCountries;
use App\Ai\Tools\GetSearchConsoleDevices;
use App\Ai\Tools\GetSearchConsoleKeywords;
use App\Ai\Tools\GetSearchConsoleKeywordsByPage;
use App\Ai\Tools\GetSearchConsolePages;
use App\Ai\Tools\GetUnderperformingSearchTerms;
use App\Ai\Tools\UpdateAdsCampaignStatus;
use App\Ai\Tools\UpdateKeywordBid;
use App\Ai\Tools\VerifyGa4TagPresence;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;

it('GetGa4Traffic is read-only', function () {
    $prop = (new ReflectionClass(GetGa4Traffic::class))->getProperty('isReadOnly');
    $prop->setAccessible(true);

    expect($prop->getValue(new GetGa4Traffic))->toBeTrue();
});

it('GetGa4Traffic description mentions GA4', function () {
    expect((string) (new GetGa4Traffic)->description())->toContain('GA4');
});

it('GetGa4Traffic schema does not contain propertyId and only has date fields', function () {
    $schema = (new GetGa4Traffic)->schema(new JsonSchemaTypeFactory);

    expect($schema)
        ->not->toHaveKey('propertyId')
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

it('GetGoogleAdsCampaigns schema does not contain customerId', function () {
    $schema = (new GetGoogleAdsCampaigns)->schema(new JsonSchemaTypeFactory);

    expect($schema)
        ->not->toHaveKey('customerId')
        ->toHaveKey('limit')
        ->not->toHaveKey('offset');
});

it('GetSearchConsoleKeywords is read-only', function () {
    $prop = (new ReflectionClass(GetSearchConsoleKeywords::class))->getProperty('isReadOnly');
    $prop->setAccessible(true);

    expect($prop->getValue(new GetSearchConsoleKeywords))->toBeTrue();
});

it('GetSearchConsoleKeywords description mentions Search Console', function () {
    expect((string) (new GetSearchConsoleKeywords)->description())->toContain('Search Console');
});

it('GetSearchConsoleKeywords schema does not contain siteUrl', function () {
    $schema = (new GetSearchConsoleKeywords)->schema(new JsonSchemaTypeFactory);

    expect($schema)
        ->not->toHaveKey('siteUrl')
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

it('UpdateAdsCampaignStatus schema does not contain customerId', function () {
    $schema = (new UpdateAdsCampaignStatus)->schema(new JsonSchemaTypeFactory);

    expect($schema)
        ->not->toHaveKey('customerId')
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

it('GetGa4Conversions schema does not contain propertyId', function () {
    $schema = (new GetGa4Conversions)->schema(new JsonSchemaTypeFactory);

    expect($schema)
        ->not->toHaveKey('propertyId')
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

it('GetAccountPerformanceSummary schema only contains date fields', function () {
    $schema = (new GetAccountPerformanceSummary)->schema(new JsonSchemaTypeFactory);

    expect($schema)
        ->not->toHaveKey('propertyId')
        ->not->toHaveKey('customerId')
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

it('GetUnderperformingSearchTerms schema has no arguments', function () {
    $schema = (new GetUnderperformingSearchTerms)->schema(new JsonSchemaTypeFactory);

    expect($schema)
        ->not->toHaveKey('customerId')
        ->toBeEmpty();
});

it('UpdateKeywordBid requires approval', function () {
    $prop = (new ReflectionClass(UpdateKeywordBid::class))->getProperty('requiresApproval');
    $prop->setAccessible(true);

    expect($prop->getValue(new UpdateKeywordBid))->toBeTrue();
});

it('UpdateKeywordBid description mentions approval', function () {
    expect((string) (new UpdateKeywordBid)->description())->toContain('approval');
});

it('UpdateKeywordBid schema does not contain customerId', function () {
    $schema = (new UpdateKeywordBid)->schema(new JsonSchemaTypeFactory);

    expect($schema)
        ->not->toHaveKey('customerId')
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

it('GetGa4TrafficSources is read-only', function () {
    $prop = (new ReflectionClass(GetGa4TrafficSources::class))->getProperty('isReadOnly');
    $prop->setAccessible(true);

    expect($prop->getValue(new GetGa4TrafficSources))->toBeTrue();
});

it('GetGa4TrafficSources description mentions channel', function () {
    expect((string) (new GetGa4TrafficSources)->description())->toContain('channel');
});

it('GetGa4TrafficSources schema only contains date fields', function () {
    $schema = (new GetGa4TrafficSources)->schema(new JsonSchemaTypeFactory);

    expect($schema)
        ->toHaveKey('startDate')
        ->toHaveKey('endDate')
        ->not->toHaveKey('propertyId');
});

it('GetGa4LandingPagePerformance is read-only', function () {
    $prop = (new ReflectionClass(GetGa4LandingPagePerformance::class))->getProperty('isReadOnly');
    $prop->setAccessible(true);

    expect($prop->getValue(new GetGa4LandingPagePerformance))->toBeTrue();
});

it('GetGa4LandingPagePerformance description mentions bounce rate', function () {
    expect((string) (new GetGa4LandingPagePerformance)->description())->toContain('bounce rate');
});

it('GetGa4LandingPagePerformance schema contains date and limit fields', function () {
    $schema = (new GetGa4LandingPagePerformance)->schema(new JsonSchemaTypeFactory);

    expect($schema)
        ->toHaveKey('startDate')
        ->toHaveKey('endDate')
        ->toHaveKey('limit')
        ->not->toHaveKey('propertyId');
});

it('GetGa4ECommerceItemSales is read-only', function () {
    $prop = (new ReflectionClass(GetGa4ECommerceItemSales::class))->getProperty('isReadOnly');
    $prop->setAccessible(true);

    expect($prop->getValue(new GetGa4ECommerceItemSales))->toBeTrue();
});

it('GetGa4ECommerceItemSales description mentions revenue', function () {
    expect((string) (new GetGa4ECommerceItemSales)->description())->toContain('revenue');
});

it('GetGa4ECommerceItemSales schema contains date and limit fields', function () {
    $schema = (new GetGa4ECommerceItemSales)->schema(new JsonSchemaTypeFactory);

    expect($schema)
        ->toHaveKey('startDate')
        ->toHaveKey('endDate')
        ->toHaveKey('limit')
        ->not->toHaveKey('propertyId');
});

it('VerifyGa4TagPresence is read-only', function () {
    $prop = (new ReflectionClass(VerifyGa4TagPresence::class))->getProperty('isReadOnly');
    $prop->setAccessible(true);

    expect($prop->getValue(new VerifyGa4TagPresence))->toBeTrue();
});

it('VerifyGa4TagPresence description mentions tracking', function () {
    expect((string) (new VerifyGa4TagPresence)->description())->toContain('tracking');
});

it('VerifyGa4TagPresence schema has no arguments', function () {
    $schema = (new VerifyGa4TagPresence)->schema(new JsonSchemaTypeFactory);

    expect($schema)->toBeEmpty();
});

it('GetSearchConsolePages is read-only', function () {
    $prop = (new ReflectionClass(GetSearchConsolePages::class))->getProperty('isReadOnly');
    $prop->setAccessible(true);

    expect($prop->getValue(new GetSearchConsolePages))->toBeTrue();
});

it('GetSearchConsolePages description mentions organic traffic', function () {
    expect((string) (new GetSearchConsolePages)->description())->toContain('organic traffic');
});

it('GetSearchConsolePages schema has date fields', function () {
    $schema = (new GetSearchConsolePages)->schema(new JsonSchemaTypeFactory);

    expect($schema)
        ->toHaveKey('startDate')
        ->toHaveKey('endDate');
});

it('GetSearchConsoleKeywordsByPage is read-only', function () {
    $prop = (new ReflectionClass(GetSearchConsoleKeywordsByPage::class))->getProperty('isReadOnly');
    $prop->setAccessible(true);

    expect($prop->getValue(new GetSearchConsoleKeywordsByPage))->toBeTrue();
});

it('GetSearchConsoleKeywordsByPage description mentions CTR', function () {
    expect((string) (new GetSearchConsoleKeywordsByPage)->description())->toContain('CTR');
});

it('GetSearchConsoleKeywordsByPage schema has date fields', function () {
    $schema = (new GetSearchConsoleKeywordsByPage)->schema(new JsonSchemaTypeFactory);

    expect($schema)
        ->toHaveKey('startDate')
        ->toHaveKey('endDate');
});

it('GetSearchConsoleDevices is read-only', function () {
    $prop = (new ReflectionClass(GetSearchConsoleDevices::class))->getProperty('isReadOnly');
    $prop->setAccessible(true);

    expect($prop->getValue(new GetSearchConsoleDevices))->toBeTrue();
});

it('GetSearchConsoleDevices description mentions device', function () {
    expect((string) (new GetSearchConsoleDevices)->description())->toContain('device');
});

it('GetSearchConsoleDevices schema has date fields', function () {
    $schema = (new GetSearchConsoleDevices)->schema(new JsonSchemaTypeFactory);

    expect($schema)
        ->toHaveKey('startDate')
        ->toHaveKey('endDate');
});

it('GetSearchConsoleCountries is read-only', function () {
    $prop = (new ReflectionClass(GetSearchConsoleCountries::class))->getProperty('isReadOnly');
    $prop->setAccessible(true);

    expect($prop->getValue(new GetSearchConsoleCountries))->toBeTrue();
});

it('GetSearchConsoleCountries description mentions geographic', function () {
    expect((string) (new GetSearchConsoleCountries)->description())->toContain('geographic');
});

it('GetSearchConsoleCountries schema has date fields', function () {
    $schema = (new GetSearchConsoleCountries)->schema(new JsonSchemaTypeFactory);

    expect($schema)
        ->toHaveKey('startDate')
        ->toHaveKey('endDate');
});

it('CheckWebsiteStatus is read-only', function () {
    $prop = (new ReflectionClass(CheckWebsiteStatus::class))->getProperty('isReadOnly');
    $prop->setAccessible(true);

    expect($prop->getValue(new CheckWebsiteStatus))->toBeTrue();
});

it('CheckWebsiteStatus description mentions reachable', function () {
    expect((string) (new CheckWebsiteStatus)->description())->toContain('reachable');
});

it('CheckWebsiteStatus schema has no arguments', function () {
    $schema = (new CheckWebsiteStatus)->schema(new JsonSchemaTypeFactory);

    expect($schema)->toBeEmpty();
});

it('GetPageHtmlContent is read-only', function () {
    $prop = (new ReflectionClass(GetPageHtmlContent::class))->getProperty('isReadOnly');
    $prop->setAccessible(true);

    expect($prop->getValue(new GetPageHtmlContent))->toBeTrue();
});

it('GetPageHtmlContent description mentions SEO', function () {
    expect((string) (new GetPageHtmlContent)->description())->toContain('SEO');
});

it('GetPageHtmlContent schema requires url', function () {
    $schema = (new GetPageHtmlContent)->schema(new JsonSchemaTypeFactory);

    expect($schema)->toHaveKey('url');
});
