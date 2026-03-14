<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Services\GoogleApiService;
use Google\Analytics\Data\V1beta\DateRange;
use Google\Analytics\Data\V1beta\Dimension;
use Google\Analytics\Data\V1beta\Filter;
use Google\Analytics\Data\V1beta\Filter\StringFilter;
use Google\Analytics\Data\V1beta\Filter\StringFilter\MatchType;
use Google\Analytics\Data\V1beta\FilterExpression;
use Google\Analytics\Data\V1beta\Metric;
use Google\Analytics\Data\V1beta\RunReportRequest;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Stringable;

/**
 * Fetches GA4 conversion event data for a given property and date range.
 */
class GetGa4Conversions extends AbstractAgentTool
{
    protected bool $isReadOnly = true;

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Fetch GA4 conversion data (conversions and event counts) for a given property ID and date range. Optionally filter by a specific event name.';
    }

    /**
     * Execute the tool's core business logic.
     *
     * @param  array{propertyId: string, startDate: string, endDate: string, eventName?: string}  $arguments
     * @return array<int, array{eventName: string, conversions: string, eventCount: string}>
     */
    public function execute(array $arguments): array
    {
        $service = new GoogleApiService(Auth::user());
        $client = $service->makeAnalyticsClient();

        $requestParams = [
            'property' => 'properties/'.$arguments['propertyId'],
            'date_ranges' => [
                new DateRange([
                    'start_date' => $arguments['startDate'],
                    'end_date' => $arguments['endDate'],
                ]),
            ],
            'metrics' => [
                new Metric(['name' => 'conversions']),
                new Metric(['name' => 'eventCount']),
            ],
            'dimensions' => [
                new Dimension(['name' => 'eventName']),
            ],
        ];

        if (! empty($arguments['eventName'])) {
            $stringFilter = new StringFilter([
                'match_type' => MatchType::EXACT,
                'value' => $arguments['eventName'],
            ]);

            $filter = new Filter([
                'field_name' => 'eventName',
                'string_filter' => $stringFilter,
            ]);

            $requestParams['dimension_filter'] = new FilterExpression(['filter' => $filter]);
        }

        $request = new RunReportRequest($requestParams);
        $response = $client->runReport($request);

        $rows = [];

        foreach ($response->getRows() as $row) {
            $dimensions = $row->getDimensionValues();
            $metrics = $row->getMetricValues();

            $rows[] = [
                'eventName' => $dimensions[0]->getValue(),
                'conversions' => $metrics[0]->getValue(),
                'eventCount' => $metrics[1]->getValue(),
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
            'eventName' => $schema->string(),
        ];
    }
}
