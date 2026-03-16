<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\Attributes\Category;
use App\Enums\ToolCategory;
use Google\Analytics\Data\V1beta\DateRange;
use Google\Analytics\Data\V1beta\Dimension;
use Google\Analytics\Data\V1beta\Metric;
use Google\Analytics\Data\V1beta\OrderBy;
use Google\Analytics\Data\V1beta\OrderBy\MetricOrderBy;
use Google\Analytics\Data\V1beta\RunReportRequest;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Stringable;

/**
 * Fetches GA4 landing page performance including bounce rate and sessions, ordered by most-visited pages.
 */
#[Category(ToolCategory::GoogleAnalytics)]
class GetGa4LandingPagePerformance extends AbstractAgentTool
{
    protected bool $isReadOnly = true;

    /**
     * {@inheritDoc}
     */
    public function label(array $arguments = []): string
    {
        return 'GA4 Landing Pages';
    }

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Fetch GA4 landing page performance (sessions, bounce rate, engagement rate) for a date range. Use this to detect broken or underperforming entry points — e.g. a homepage with a 95% bounce rate after an ad campaign change.';
    }

    /**
     * Execute the tool's core business logic.
     *
     * @param  array{startDate: string, endDate: string, limit?: int}  $arguments
     * @return array<int, array{landingPage: string, sessions: string, bounceRate: string, engagementRate: string}>
     */
    public function execute(array $arguments): array
    {
        $propertyId = $this->shop?->ga4_property_id
            ?? throw new \RuntimeException('Shop has no GA4 property ID configured.');

        $limit = $arguments['limit'] ?? 10;

        $service = $this->googleApiService();
        $client = $service->makeAnalyticsClient();

        $request = new RunReportRequest([
            'property' => 'properties/'.$propertyId,
            'date_ranges' => [
                new DateRange([
                    'start_date' => $arguments['startDate'],
                    'end_date' => $arguments['endDate'],
                ]),
            ],
            'dimensions' => [
                new Dimension(['name' => 'landingPagePlusQueryString']),
            ],
            'metrics' => [
                new Metric(['name' => 'sessions']),
                new Metric(['name' => 'bounceRate']),
                new Metric(['name' => 'engagementRate']),
            ],
            'order_bys' => [
                new OrderBy([
                    'metric' => new MetricOrderBy(['metric_name' => 'sessions']),
                    'desc' => true,
                ]),
            ],
            'limit' => $limit,
        ]);

        $response = $client->runReport($request);

        $rows = [];

        foreach ($response->getRows() as $row) {
            $dimensions = $row->getDimensionValues();
            $metrics = $row->getMetricValues();

            $rows[] = [
                'landingPage' => $dimensions[0]->getValue(),
                'sessions' => $metrics[0]->getValue(),
                'bounceRate' => $metrics[1]->getValue(),
                'engagementRate' => $metrics[2]->getValue(),
            ];
        }

        return $rows;
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'startDate' => $schema->string()->required(),
            'endDate' => $schema->string()->required(),
            'limit' => $schema->integer(),
        ];
    }
}
