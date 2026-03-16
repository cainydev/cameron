<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\Attributes\Category;
use App\Enums\ToolCategory;
use Google\Analytics\Data\V1beta\DateRange;
use Google\Analytics\Data\V1beta\Metric;
use Google\Analytics\Data\V1beta\RunReportRequest;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Stringable;

/**
 * Fetches GA4 traffic metrics (sessions + pageviews) for the shop's property and a given date range.
 */
#[Category(ToolCategory::GoogleAnalytics)]
class GetGa4Traffic extends AbstractAgentTool
{
    protected bool $isReadOnly = true;

    /**
     * {@inheritDoc}
     */
    public function label(array $arguments = []): string
    {
        if (! empty($arguments['startDate']) && ! empty($arguments['endDate'])) {
            return "GA4 Traffic ({$arguments['startDate']} – {$arguments['endDate']})";
        }

        return 'GA4 Traffic';
    }

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Fetch GA4 traffic data (sessions and page views) for the configured property and date range.';
    }

    /**
     * Execute the tool's core business logic.
     *
     * @param  array{startDate: string, endDate: string}  $arguments
     * @return array<int, array{sessions: string, pageViews: string}>
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
            'metrics' => [
                new Metric(['name' => 'sessions']),
                new Metric(['name' => 'screenPageViews']),
            ],
        ]);

        $response = $client->runReport($request);

        $rows = [];

        foreach ($response->getRows() as $row) {
            $metrics = $row->getMetricValues();
            $rows[] = [
                'sessions' => $metrics[0]->getValue(),
                'pageViews' => $metrics[1]->getValue(),
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
