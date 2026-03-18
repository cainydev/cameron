<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\Attributes\Category;
use App\Ai\Concerns\FormatsToolOutput;
use App\Enums\ToolCategory;
use Google\Service\Webmasters\SearchAnalyticsQueryRequest;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Stringable;

/**
 * Fetches top search keywords from Google Search Console for the shop's site.
 */
#[Category(ToolCategory::SearchConsole)]
class GetSearchConsoleKeywords extends AbstractAgentTool
{
    use FormatsToolOutput;

    protected bool $isReadOnly = true;

    /**
     * {@inheritDoc}
     */
    public function label(array $arguments = []): string
    {
        return 'Search Console Keywords';
    }

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Fetch top search keywords from Google Search Console for the configured site and date range.';
    }

    /**
     * Execute the tool's core business logic.
     *
     * @param  array{startDate: string, endDate: string, limit?: int}  $arguments
     * @return array<int, array{query: string, clicks: float, impressions: float, ctr: float, position: float}>
     */
    public function execute(array $arguments): array
    {
        $siteUrl = $this->shop?->search_console_url
            ?? throw new \RuntimeException('Shop has no Search Console URL configured.');

        $limit = $arguments['limit'] ?? 100;

        $service = $this->googleApiService();
        $webmasters = $service->makeSearchConsoleClient();

        $queryRequest = new SearchAnalyticsQueryRequest;
        $queryRequest->setDimensions(['query']);
        $queryRequest->setStartDate($arguments['startDate']);
        $queryRequest->setEndDate($arguments['endDate']);
        $queryRequest->setRowLimit($limit);

        $response = $webmasters->searchanalytics->query($siteUrl, $queryRequest);

        $rows = [];

        foreach ($response->getRows() as $row) {
            $rows[] = [
                'query' => $row->getKeys()[0] ?? '',
                'clicks' => (int) $row->getClicks(),
                'impressions' => (int) $row->getImpressions(),
                'ctr' => $this->toPercent($row->getCtr()),
                'position' => $this->toPosition($row->getPosition()),
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
