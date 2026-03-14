<?php

use App\Models\User;
use App\Services\GoogleAccountDiscoveryService;
use Google\Service\GoogleAnalyticsAdmin\GoogleAnalyticsAdminV1betaAccountSummary;
use Google\Service\GoogleAnalyticsAdmin\GoogleAnalyticsAdminV1betaListAccountSummariesResponse;
use Google\Service\GoogleAnalyticsAdmin\GoogleAnalyticsAdminV1betaPropertySummary;
use Google\Service\Webmasters\SitesListResponse;
use Google\Service\Webmasters\WmxSite;

beforeEach(function () {
    $this->user = User::factory()->create(['google_refresh_token' => 'test-refresh-token']);
});

it('can be instantiated with a user', function () {
    $service = new GoogleAccountDiscoveryService($this->user);

    expect($service)->toBeInstanceOf(GoogleAccountDiscoveryService::class);
});

it('getAccessibleAdsCustomers returns empty array when credentials are invalid', function () {
    $service = new GoogleAccountDiscoveryService($this->user);

    expect($service->getAccessibleAdsCustomers())->toBeArray()->toBeEmpty();
});

it('getAccessibleSearchConsoleSites returns empty array when credentials are invalid', function () {
    $service = new GoogleAccountDiscoveryService($this->user);

    expect($service->getAccessibleSearchConsoleSites())->toBeArray()->toBeEmpty();
});

it('getAccessibleGa4Properties returns empty array when credentials are invalid', function () {
    $service = new GoogleAccountDiscoveryService($this->user);

    expect($service->getAccessibleGa4Properties())->toBeArray()->toBeEmpty();
});

it('strips customers/ prefix when building Ads customer array shape', function () {
    $resourceNames = ['customers/1234567890', 'customers/9876543210'];

    $customers = array_map(function (string $resourceName): array {
        $id = str_replace('customers/', '', $resourceName);

        return ['id' => $id, 'name' => $id];
    }, $resourceNames);

    expect($customers)
        ->toHaveCount(2)
        ->and($customers[0])->toMatchArray(['id' => '1234567890', 'name' => '1234567890'])
        ->and($customers[1])->toMatchArray(['id' => '9876543210', 'name' => '9876543210']);
});

it('maps Search Console WmxSite entries to url and permissionLevel shape', function () {
    $site1 = Mockery::mock(WmxSite::class);
    $site1->shouldReceive('getSiteUrl')->andReturn('https://example.com/');
    $site1->shouldReceive('getPermissionLevel')->andReturn('siteOwner');

    $site2 = Mockery::mock(WmxSite::class);
    $site2->shouldReceive('getSiteUrl')->andReturn('https://other.com/');
    $site2->shouldReceive('getPermissionLevel')->andReturn('siteFullUser');

    $response = Mockery::mock(SitesListResponse::class);
    $response->shouldReceive('getSiteEntry')->andReturn([$site1, $site2]);

    $sites = [];
    foreach ($response->getSiteEntry() as $entry) {
        $sites[] = [
            'url' => $entry->getSiteUrl(),
            'permissionLevel' => $entry->getPermissionLevel(),
        ];
    }

    expect($sites)
        ->toHaveCount(2)
        ->and($sites[0])->toMatchArray(['url' => 'https://example.com/', 'permissionLevel' => 'siteOwner'])
        ->and($sites[1])->toMatchArray(['url' => 'https://other.com/', 'permissionLevel' => 'siteFullUser']);
});

it('strips properties/ prefix and groups GA4 properties under account name', function () {
    $property1 = Mockery::mock(GoogleAnalyticsAdminV1betaPropertySummary::class);
    $property1->shouldReceive('getProperty')->andReturn('properties/11111');
    $property1->shouldReceive('getDisplayName')->andReturn('My Website');

    $property2 = Mockery::mock(GoogleAnalyticsAdminV1betaPropertySummary::class);
    $property2->shouldReceive('getProperty')->andReturn('properties/22222');
    $property2->shouldReceive('getDisplayName')->andReturn('Other Site');

    $account = Mockery::mock(GoogleAnalyticsAdminV1betaAccountSummary::class);
    $account->shouldReceive('getDisplayName')->andReturn('Acme Corp');
    $account->shouldReceive('getPropertySummaries')->andReturn([$property1, $property2]);

    $response = Mockery::mock(GoogleAnalyticsAdminV1betaListAccountSummariesResponse::class);
    $response->shouldReceive('getAccountSummaries')->andReturn([$account]);

    $properties = [];
    foreach ($response->getAccountSummaries() as $summary) {
        $accountName = $summary->getDisplayName();
        foreach ($summary->getPropertySummaries() as $propertySummary) {
            $id = str_replace('properties/', '', $propertySummary->getProperty());
            $properties[] = [
                'id' => $id,
                'name' => $propertySummary->getDisplayName(),
                'account_name' => $accountName,
            ];
        }
    }

    expect($properties)
        ->toHaveCount(2)
        ->and($properties[0])->toMatchArray(['id' => '11111', 'name' => 'My Website', 'account_name' => 'Acme Corp'])
        ->and($properties[1])->toMatchArray(['id' => '22222', 'name' => 'Other Site', 'account_name' => 'Acme Corp']);
});

it('flattens GA4 properties across multiple accounts preserving each account name', function () {
    $property1 = Mockery::mock(GoogleAnalyticsAdminV1betaPropertySummary::class);
    $property1->shouldReceive('getProperty')->andReturn('properties/100');
    $property1->shouldReceive('getDisplayName')->andReturn('Site Alpha');

    $property2 = Mockery::mock(GoogleAnalyticsAdminV1betaPropertySummary::class);
    $property2->shouldReceive('getProperty')->andReturn('properties/200');
    $property2->shouldReceive('getDisplayName')->andReturn('Site Beta');

    $account1 = Mockery::mock(GoogleAnalyticsAdminV1betaAccountSummary::class);
    $account1->shouldReceive('getDisplayName')->andReturn('Account One');
    $account1->shouldReceive('getPropertySummaries')->andReturn([$property1]);

    $account2 = Mockery::mock(GoogleAnalyticsAdminV1betaAccountSummary::class);
    $account2->shouldReceive('getDisplayName')->andReturn('Account Two');
    $account2->shouldReceive('getPropertySummaries')->andReturn([$property2]);

    $response = Mockery::mock(GoogleAnalyticsAdminV1betaListAccountSummariesResponse::class);
    $response->shouldReceive('getAccountSummaries')->andReturn([$account1, $account2]);

    $properties = [];
    foreach ($response->getAccountSummaries() as $summary) {
        $accountName = $summary->getDisplayName();
        foreach ($summary->getPropertySummaries() as $propertySummary) {
            $id = str_replace('properties/', '', $propertySummary->getProperty());
            $properties[] = [
                'id' => $id,
                'name' => $propertySummary->getDisplayName(),
                'account_name' => $accountName,
            ];
        }
    }

    expect($properties)
        ->toHaveCount(2)
        ->and($properties[0])->toMatchArray(['id' => '100', 'account_name' => 'Account One'])
        ->and($properties[1])->toMatchArray(['id' => '200', 'account_name' => 'Account Two']);
});
