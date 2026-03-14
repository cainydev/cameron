<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use Google\Service\Webmasters\SearchAnalyticsQueryRequest;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Stringable;

/**
 * Fetches top-ranking pages from Google Search Console for the shop's site.
 */
class GetSearchConsolePages extends AbstractAgentTool
{
    protected bool $isReadOnly = true;

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Fetch top-ranking pages from Google Search Console ordered by clicks. Returns URL, clicks, impressions, CTR, and average position. Use to identify which pages drive the most organic traffic.';
    }

    /**
     * Execute the tool's core business logic.
     *
     * @param  array{startDate: string, endDate: string, limit?: int}  $arguments
     * @return array<int, array{page: string, clicks: float, impressions: float, ctr: float, position: float}>
     */
    public function execute(array $arguments): array
    {
        $siteUrl = $this->shop?->search_console_url
            ?? throw new \RuntimeException('Shop has no Search Console URL configured.');

        $limit = $arguments['limit'] ?? 10;

        $service = $this->googleApiService();
        $webmasters = $service->makeSearchConsoleClient();

        $queryRequest = new SearchAnalyticsQueryRequest;
        $queryRequest->setDimensions(['page']);
        $queryRequest->setStartDate($arguments['startDate']);
        $queryRequest->setEndDate($arguments['endDate']);
        $queryRequest->setRowLimit($limit);

        $response = $webmasters->searchanalytics->query($siteUrl, $queryRequest);

        $rows = [];

        foreach ($response->getRows() as $row) {
            $rows[] = [
                'page' => $row->getKeys()[0] ?? '',
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
