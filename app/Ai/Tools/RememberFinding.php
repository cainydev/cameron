<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\CameronMemory;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Stringable;

/**
 * Persists a finding or insight into Cameron's long-term memory for this shop.
 */
class RememberFinding extends AbstractAgentTool
{
    protected bool $isReadOnly = false;

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Save a finding, insight, or important fact to long-term memory. Use this whenever you discover something worth remembering across conversations — a performance trend, a structural issue, a user preference, or a key metric. Include a category to help with retrieval.';
    }

    /**
     * Execute the tool's core business logic.
     *
     * @param  array{content: string, category: string}  $arguments
     * @return array{success: bool, id: int, recorded_at: string}
     */
    public function execute(array $arguments): array
    {
        $shopId = $this->shop?->id
            ?? throw new \RuntimeException('No shop context set for RememberFinding.');

        $memory = CameronMemory::query()->create([
            'shop_id' => $shopId,
            'category' => $arguments['category'],
            'content' => $arguments['content'],
            'recorded_at' => now(),
        ]);

        return [
            'success' => true,
            'id' => $memory->id,
            'recorded_at' => $memory->recorded_at->toIso8601String(),
        ];
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'content' => $schema->string()->required(),
            'category' => $schema->string()->enum([
                'performance',
                'campaign',
                'seo',
                'conversion',
                'budget',
                'audience',
                'product',
                'general',
            ])->required(),
        ];
    }
}
