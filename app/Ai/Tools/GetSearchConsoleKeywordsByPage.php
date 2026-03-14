<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use Google\Service\Webmasters\SearchAnalyticsQueryRequest;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Stringable;

/**
 * Fetches search keywords broken down by page from Google Search Console.
 */
class GetSearchConsoleKeywordsByPage extends AbstractAgentTool
{
    protected bool $isReadOnly = true;

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Fetch search keywords grouped by page from Google Search Console. Shows which queries drive traffic to which URLs. Use to find CTR optimisation opportunities and keyword-to-page mapping gaps.';
    }

    /**
     * Execute the tool's core business logic.
     *
     * @param  array{startDate: string, endDate: string, limit?: int}  $arguments
     * @return array<int, array{query: string, page: string, clicks: float, impressions: float, ctr: float, position: float}>
     */
    public function execute(array $arguments): array
    {
        $siteUrl = $this->shop?->search_console_url
            ?? throw new \RuntimeException('Shop has no Search Console URL configured.');

        $limit = $arguments['limit'] ?? 20;

        $service = $this->googleApiService();
        $webmasters = $service->makeSearchConsoleClient();

        $queryRequest = new SearchAnalyticsQueryRequest;
        $queryRequest->setDimensions(['query', 'page']);
        $queryRequest->setStartDate($arguments['startDate']);
        $queryRequest->setEndDate($arguments['endDate']);
        $queryRequest->setRowLimit($limit);

        $response = $webmasters->searchanalytics->query($siteUrl, $queryRequest);

        $rows = [];

        foreach ($response->getRows() as $row) {
            $keys = $row->getKeys();
            $rows[] = [
                'query' => $keys[0] ?? '',
                'page' => $keys[1] ?? '',
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
            'startDate' => $schema->string()->required(),
            'endDate' => $schema->string()->required(),
            'limit' => $schema->integer(),
        ];
    }
}
