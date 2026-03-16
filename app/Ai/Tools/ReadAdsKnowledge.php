<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\Attributes\Category;
use App\Enums\ToolCategory;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\File;
use Stringable;

/**
 * Returns specialist Google Ads knowledge from the internal guideline library.
 */
#[Category(ToolCategory::GoogleAds)]
class ReadAdsKnowledge extends AbstractAgentTool
{
    protected bool $isReadOnly = true;

    /**
     * {@inheritDoc}
     */
    public function label(array $arguments = []): string
    {
        if (! empty($arguments['topic'])) {
            return 'Ads Knowledge: '.ucfirst($arguments['topic']);
        }

        return 'Ads Knowledge';
    }

    /**
     * This tool reads local knowledge files and does not require any shop fields.
     *
     * @return list<string>
     */
    public function requiredShopFields(): array
    {
        return [];
    }

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return <<<'DESC'
        Retrieve a specialist Google Ads knowledge module. Call this before making any significant recommendation or diagnosis. Available topics:
        - diagnostics: Performance anomalies, sudden drops, bleeding spend, troubleshooting protocols.
        - optimization: Scaling decisions, bid adjustments, budget reallocation, performance optimization.
        - architecture: Campaign restructuring, segmentation decisions, new campaign creation, architectural diagnosis.
        - pmax: Performance Max creation, optimization, cannibalization concerns, black-box opacity.
        - products: SKU-level analysis, product performance audits, inventory optimization, feed management.
        DESC;
    }

    /**
     * Execute the tool's core business logic.
     *
     * @param  array{topic: string}  $arguments
     * @return array{topic: string, content: string}
     */
    public function execute(array $arguments): array
    {
        $topic = $arguments['topic'];
        $path = resource_path("prompts/ads_{$topic}.md");

        if (! File::exists($path)) {
            throw new \RuntimeException("Unknown ads knowledge topic: {$topic}.");
        }

        return [
            'topic' => $topic,
            'content' => File::get($path),
        ];
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'topic' => $schema->string()->enum([
                'diagnostics',
                'optimization',
                'architecture',
                'pmax',
                'products',
            ])->required(),
        ];
    }
}
