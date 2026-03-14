<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use Google\Analytics\Data\V1beta\DateRange;
use Google\Analytics\Data\V1beta\Dimension;
use Google\Analytics\Data\V1beta\Metric;
use Google\Analytics\Data\V1beta\OrderBy;
use Google\Analytics\Data\V1beta\OrderBy\MetricOrderBy;
use Google\Analytics\Data\V1beta\RunReportRequest;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Stringable;

/**
 * Fetches GA4 e-commerce item sales data (item name, revenue, quantity) for a date range.
 */
class GetGa4ECommerceItemSales extends AbstractAgentTool
{
    protected bool $isReadOnly = true;

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Fetch GA4 e-commerce item sales (item name, revenue, quantity sold) for a date range, ordered by revenue. Use this to identify which products are driving revenue and correlate with ad spend.';
    }

    /**
     * Execute the tool's core business logic.
     *
     * @param  array{startDate: string, endDate: string, limit?: int}  $arguments
     * @return array<int, array{itemName: string, itemRevenue: string, itemsPurchased: string}>
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
                new Dimension(['name' => 'itemName']),
            ],
            'metrics' => [
                new Metric(['name' => 'itemRevenue']),
                new Metric(['name' => 'itemsPurchased']),
            ],
            'order_bys' => [
                new OrderBy([
                    'metric' => new MetricOrderBy(['metric_name' => 'itemRevenue']),
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
                'itemName' => $dimensions[0]->getValue(),
                'itemRevenue' => $metrics[0]->getValue(),
                'itemsPurchased' => $metrics[1]->getValue(),
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
