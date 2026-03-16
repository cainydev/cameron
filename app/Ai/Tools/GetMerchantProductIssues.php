<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\Attributes\Category;
use App\Enums\ToolCategory;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Stringable;

/**
 * Retrieves product-level issues and disapproval reasons from Google Merchant Center.
 */
#[Category(ToolCategory::GoogleAds)]
class GetMerchantProductIssues extends AbstractAgentTool
{
    protected bool $isReadOnly = true;

    /**
     * {@inheritDoc}
     */
    public function requiredShopFields(): array
    {
        return ['merchant_center_id'];
    }

    /**
     * {@inheritDoc}
     */
    public function label(array $arguments = []): string
    {
        return 'Merchant Product Issues';
    }

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Retrieve disapproval reasons and item-level issues for products in Google Merchant Center via the Reports API. Use to identify feed quality problems that hurt PMax and Shopping campaign performance.';
    }

    /**
     * @param  array{pageSize?: int, pageToken?: string}  $arguments
     * @return array{merchantId: string, products: array<int, array{id: string, title: string, issues: array<int, array{code: string, attribute: string|null, severity: string, reportingContexts: list<string>}>}>}
     */
    public function execute(array $arguments): array
    {
        $merchantId = $this->shop?->merchant_center_id
            ?? throw new \RuntimeException('Shop has no Merchant Center ID configured.');

        $pageSize = $arguments['pageSize'] ?? 50;

        $body = [
            'query' => "SELECT product_view.id, product_view.title, product_view.item_issues
                        FROM product_view
                        WHERE product_view.item_issues IS NOT NULL
                        LIMIT {$pageSize}",
        ];

        if (! empty($arguments['pageToken'])) {
            $body['pageToken'] = $arguments['pageToken'];
        }

        $client = $this->googleApiService()->makeMerchantApiClient();
        $response = $client->post("reports/v1beta/accounts/{$merchantId}/reports:search", $body);

        if ($response->failed()) {
            throw new \RuntimeException("Merchant API error: HTTP {$response->status()}");
        }

        $products = [];

        foreach ($response->json('results', []) as $row) {
            $view = $row['productView'] ?? [];
            $issues = [];

            foreach ($view['itemIssues'] ?? [] as $issue) {
                $contexts = [];

                foreach ($issue['severity']['severityPerReportingContext'] ?? [] as $ctx) {
                    $contexts[] = $ctx['reportingContext'];
                }

                $issues[] = [
                    'code' => $issue['type']['code'] ?? '',
                    'attribute' => $issue['type']['canonicalAttribute'] ?? null,
                    'severity' => $issue['severity']['maxSeverity'] ?? 'UNKNOWN',
                    'reportingContexts' => $contexts,
                ];
            }

            if ($issues === []) {
                continue;
            }

            $products[] = [
                'id' => $view['id'] ?? '',
                'title' => $view['title'] ?? '',
                'issues' => $issues,
            ];
        }

        return [
            'merchantId' => $merchantId,
            'products' => $products,
        ];
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'pageSize' => $schema->integer(),
            'pageToken' => $schema->string(),
        ];
    }
}
