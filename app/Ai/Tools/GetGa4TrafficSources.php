<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\Attributes\Category;
use App\Ai\Concerns\FormatsToolOutput;
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
 * Fetches GA4 session breakdown by default channel group (Organic Search, Paid Search, Direct, etc.).
 */
#[Category(ToolCategory::GoogleAnalytics)]
class GetGa4TrafficSources extends AbstractAgentTool
{
    use FormatsToolOutput;

    protected bool $isReadOnly = true;

    /**
     * {@inheritDoc}
     */
    public function label(array $arguments = []): string
    {
        return 'GA4 Traffic by Channel';
    }

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Fetch GA4 traffic broken down by channel (Organic Search, Paid Search, Direct, Referral, etc.) for a date range. Use this to diagnose whether a traffic drop came from a specific source such as ads or SEO.';
    }

    /**
     * Execute the tool's core business logic.
     *
     * @param  array{startDate: string, endDate: string}  $arguments
     * @return array<int, array{channel: string, sessions: string, engagementRate: string}>
     */
    public function execute(array $arguments): array
    {
        $propertyId = $this->shop?->ga4_property_id
            ?? throw new \RuntimeException('Shop has no GA4 property ID configured.');

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
                new Dimension(['name' => 'sessionDefaultChannelGroup']),
            ],
            'metrics' => [
                new Metric(['name' => 'sessions']),
                new Metric(['name' => 'engagementRate']),
            ],
            'order_bys' => [
                new OrderBy([
                    'metric' => new MetricOrderBy(['metric_name' => 'sessions']),
                    'desc' => true,
                ]),
            ],
        ]);

        $response = $client->runReport($request);

        $rows = [];

        foreach ($response->getRows() as $row) {
            $dimensions = $row->getDimensionValues();
            $metrics = $row->getMetricValues();

            $rows[] = [
                'channel' => $dimensions[0]->getValue(),
                'sessions' => (int) $metrics[0]->getValue(),
                'engagementRate' => $this->toPercent((float) $metrics[1]->getValue()),
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
        ];
    }
}
