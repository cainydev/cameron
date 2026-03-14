<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Services\GoogleApiService;
use Google\Service\Webmasters\SearchAnalyticsQueryRequest;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Stringable;

/**
 * Fetches top search keywords from Google Search Console for a given site.
 */
class GetSearchConsoleKeywords extends AbstractAgentTool
{
    protected bool $isReadOnly = true;

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Fetch top search keywords from Google Search Console for a given site URL and date range.';
    }

    /**
     * Execute the tool's core business logic.
     *
     * @param  array{siteUrl: string, startDate: string, endDate: string, limit?: int}  $arguments
     * @return array<int, array{query: string, clicks: float, impressions: float, ctr: float, position: float}>
     */
    public function execute(array $arguments): array
    {
        $limit = $arguments['limit'] ?? 10;

        $service = new GoogleApiService(Auth::user());
        $webmasters = $service->makeSearchConsoleClient();

        $queryRequest = new SearchAnalyticsQueryRequest;
        $queryRequest->setDimensions(['query']);
        $queryRequest->setStartDate($arguments['startDate']);
        $queryRequest->setEndDate($arguments['endDate']);
        $queryRequest->setRowLimit($limit);

        $response = $webmasters->searchanalytics->query($arguments['siteUrl'], $queryRequest);

        $rows = [];

        foreach ($response->getRows() as $row) {
            $rows[] = [
                'query' => $row->getKeys()[0] ?? '',
                'clicks' => $row->getClicks(),
                'impressions' => $row->getImpressions(),
                'ctr' => $row->getCtr(),
                'position' => $row->getPosition(),
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
            'siteUrl' => $schema->string()->required(),
            'startDate' => $schema->string()->required(),
            'endDate' => $schema->string()->required(),
            'limit' => $schema->integer(),
        ];
    }
}
