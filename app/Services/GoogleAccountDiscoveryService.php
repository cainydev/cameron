<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Google\Ads\GoogleAds\V20\Services\ListAccessibleCustomersRequest;
use Google\Service\GoogleAnalyticsAdmin;

/**
 * Discovers accessible Google accounts (Ads, Search Console, GA4) for a user.
 */
class GoogleAccountDiscoveryService
{
    public function __construct(private User $user) {}

    /**
     * Return all Google Ads customer accounts accessible by the user.
     *
     * @return array<int, array{id: string, name: string}>
     */
    public function getAccessibleAdsCustomers(): array
    {
        try {
            $service = new GoogleApiService($this->user);
            $adsClient = $service->makeAdsClient();

            $response = $adsClient->getCustomerServiceClient()->listAccessibleCustomers(
                new ListAccessibleCustomersRequest
            );

            $customers = [];

            foreach ($response->getResourceNames() as $resourceName) {
                $id = str_replace('customers/', '', $resourceName);
                $customers[] = ['id' => $id, 'name' => $id];
            }

            return $customers;
        } catch (\Google\Service\Exception $e) {
            return [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Return all Search Console sites accessible by the user.
     *
     * @return array<int, array{url: string, permissionLevel: string}>
     */
    public function getAccessibleSearchConsoleSites(): array
    {
        try {
            $service = new GoogleApiService($this->user);
            $webmasters = $service->makeSearchConsoleClient();

            $response = $webmasters->sites->listSites();

            $sites = [];

            foreach ($response->getSiteEntry() as $site) {
                $sites[] = [
                    'url' => $site->getSiteUrl(),
                    'permissionLevel' => $site->getPermissionLevel(),
                ];
            }

            return $sites;
        } catch (\Google\Service\Exception $e) {
            return [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Return all GA4 properties accessible by the user, grouped under their accounts.
     *
     * @return array<int, array{id: string, name: string, account_name: string}>
     */
    public function getAccessibleGa4Properties(): array
    {
        try {
            $service = new GoogleApiService($this->user);
            $googleClient = $service->makeGoogleClient(['https://www.googleapis.com/auth/analytics.readonly']);
            $adminService = new GoogleAnalyticsAdmin($googleClient);

            $response = $adminService->accountSummaries->listAccountSummaries();

            $properties = [];

            foreach ($response->getAccountSummaries() as $accountSummary) {
                $accountName = $accountSummary->getDisplayName();

                foreach ($accountSummary->getPropertySummaries() as $propertySummary) {
                    $id = str_replace('properties/', '', $propertySummary->getProperty());
                    $properties[] = [
                        'id' => $id,
                        'name' => $propertySummary->getDisplayName(),
                        'account_name' => $accountName,
                    ];
                }
            }

            return $properties;
        } catch (\Google\Service\Exception $e) {
            return [];
        } catch (\Exception $e) {
            return [];
        }
    }
}
