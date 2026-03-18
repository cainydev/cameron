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
 * Fetches Search Console performance broken down by country.
 */
#[Category(ToolCategory::SearchConsole)]
class GetSearchConsoleCountries extends AbstractAgentTool
{
    use FormatsToolOutput;

    protected bool $isReadOnly = true;

    /**
     * {@inheritDoc}
     */
    public function label(array $arguments = []): string
    {
        return 'Search Console by Country';
    }

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Fetch Google Search Console performance broken down by country. Use to understand geographic distribution of organic traffic and identify international SEO opportunities.';
    }

    /**
     * Execute the tool's core business logic.
     *
     * @param  array{startDate: string, endDate: string, limit?: int}  $arguments
     * @return array<int, array{country: string, clicks: float, impressions: float, ctr: float, position: float}>
     */
    public function execute(array $arguments): array
    {
        $siteUrl = $this->shop?->search_console_url
            ?? throw new \RuntimeException('Shop has no Search Console URL configured.');

        $limit = $arguments['limit'] ?? 100;

        $service = $this->googleApiService();
        $webmasters = $service->makeSearchConsoleClient();

        $queryRequest = new SearchAnalyticsQueryRequest;
        $queryRequest->setDimensions(['country']);
        $queryRequest->setStartDate($arguments['startDate']);
        $queryRequest->setEndDate($arguments['endDate']);
        $queryRequest->setRowLimit($limit);

        $response = $webmasters->searchanalytics->query($siteUrl, $queryRequest);

        $rows = [];

        foreach ($response->getRows() as $row) {
            $rows[] = [
                'country' => $row->getKeys()[0] ?? '',
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
