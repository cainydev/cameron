<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Services\GoogleApiService;
use Google\Analytics\Data\V1beta\DateRange;
use Google\Analytics\Data\V1beta\Metric;
use Google\Analytics\Data\V1beta\RunReportRequest;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Stringable;

/**
 * Fetches GA4 traffic metrics (sessions + pageviews) for a given property and date range.
 */
class GetGa4Traffic extends AbstractAgentTool
{
    protected bool $isReadOnly = true;

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Fetch GA4 traffic data (sessions and page views) for a given property ID and date range.';
    }

    /**
     * Execute the tool's core business logic.
     *
     * @param  array{propertyId: string, startDate: string, endDate: string}  $arguments
     * @return array<int, array{sessions: string, pageViews: string}>
     */
    public function execute(array $arguments): array
    {
        $service = new GoogleApiService(Auth::user());
        $client = $service->makeAnalyticsClient();

        $request = new RunReportRequest([
            'property' => 'properties/'.$arguments['propertyId'],
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
            'propertyId' => $schema->string()->required(),
            'startDate' => $schema->string()->required(),
            'endDate' => $schema->string()->required(),
        ];
    }
}
