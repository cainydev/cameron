<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\Attributes\Category;
use App\Enums\ToolCategory;
use Google\Ads\GoogleAds\V20\Services\SearchGoogleAdsRequest;
use Google\Analytics\Data\V1beta\DateRange;
use Google\Analytics\Data\V1beta\Metric;
use Google\Analytics\Data\V1beta\RunReportRequest;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Stringable;

/**
 * Fetches a high-level account performance summary combining GA4 traffic and Google Ads spend/conversions.
 */
#[Category(ToolCategory::AccountOverview)]
class GetAccountPerformanceSummary extends AbstractAgentTool
{
    protected bool $isReadOnly = true;

    /**
     * This tool requires both GA4 and Google Ads connections.
     *
     * @return list<string>
     */
    public function requiredShopFields(): array
    {
        return ['ga4_property_id', 'google_ads_customer_id'];
    }

    /**
     * {@inheritDoc}
     */
    public function label(array $arguments = []): string
    {
        return 'Account Performance Summary';
    }

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Fetch a high-level account performance summary combining GA4 traffic (sessions, pageViews) and Google Ads spend/conversions for a given date range. Use this for a quick morning briefing.';
    }

    /**
     * Execute the tool's core business logic.
     *
     * @param  array{startDate: string, endDate: string}  $arguments
     * @return array{sessions: int, pageViews: int, totalSpendMicros: int, totalClicks: int, totalConversions: float, period: array{start: string, end: string}}
     */
    public function execute(array $arguments): array
    {
        $propertyId = $this->shop?->ga4_property_id
            ?? throw new \RuntimeException('Shop has no GA4 property ID configured.');

        $customerId = $this->shop?->google_ads_customer_id
            ?? throw new \RuntimeException('Shop has no Google Ads customer ID configured.');

        $service = $this->googleApiService();

        $analyticsClient = $service->makeAnalyticsClient();
        $request = new RunReportRequest([
            'property' => 'properties/'.$propertyId,
            'date_ranges' => [
                new DateRange([
                    'start_date' => $arguments['startDate'],
                    'end_date' => $arguments['endDate'],
                ]),
            ],
            'metrics' => [
                new Metric(['name' => 'sessions']),
                new Metric(['name' => 'screenPageViews']),
            ],
        ]);

        $analyticsResponse = $analyticsClient->runReport($request);

        $sessions = 0;
        $pageViews = 0;

        foreach ($analyticsResponse->getRows() as $row) {
            $metrics = $row->getMetricValues();
            $sessions += (int) $metrics[0]->getValue();
            $pageViews += (int) $metrics[1]->getValue();
        }

        $adsClient = $service->makeAdsClient();
        $query = "SELECT metrics.cost_micros, metrics.conversions, metrics.clicks
                  FROM customer
                  WHERE segments.date BETWEEN '{$arguments['startDate']}' AND '{$arguments['endDate']}'";

        $adsRequest = new SearchGoogleAdsRequest([
            'customer_id' => $customerId,
            'query' => $query,
        ]);

        $adsResponse = $adsClient->getGoogleAdsServiceClient()->search($adsRequest);

        $totalSpendMicros = 0;
        $totalConversions = 0.0;
        $totalClicks = 0;

        foreach ($adsResponse->iterateAllElements() as $row) {
            $totalSpendMicros += $row->getMetrics()->getCostMicros();
            $totalConversions += $row->getMetrics()->getConversions();
            $totalClicks += $row->getMetrics()->getClicks();
        }

        return [
            'sessions' => $sessions,
            'pageViews' => $pageViews,
            'totalSpendMicros' => $totalSpendMicros,
            'totalClicks' => $totalClicks,
            'totalConversions' => $totalConversions,
            'period' => [
                'start' => $arguments['startDate'],
                'end' => $arguments['endDate'],
            ],
        ];
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'startDate' => $schema->string()->required(),
            'endDate' => $schema->string()->required(),
        ];
    }
}
