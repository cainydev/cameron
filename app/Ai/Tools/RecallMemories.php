<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\CameronMemory;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Stringable;

/**
 * Retrieves Cameron's stored memories for this shop, optionally filtered by category.
 */
class RecallMemories extends AbstractAgentTool
{
    protected bool $isReadOnly = true;

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Retrieve stored memories for this shop. Call this at the start of any conversation or analysis to surface relevant prior findings before fetching live data. Optionally filter by category.';
    }

    /**
     * Execute the tool's core business logic.
     *
     * @param  array{category?: string, limit?: int}  $arguments
     * @return array<int, array{id: int, category: string, content: string, recorded_at: string, age_days: int}>
     */
    public function execute(array $arguments): array
    {
        $shopId = $this->shop?->id
            ?? throw new \RuntimeException('No shop context set for RecallMemories.');

        $query = CameronMemory::query()
            ->where('shop_id', $shopId)
            ->orderByDesc('recorded_at')
            ->limit($arguments['limit'] ?? 20);

        if (! empty($arguments['category'])) {
            $query->where('category', $arguments['category']);
        }

        return $query->get()->map(fn (CameronMemory $m) => [
            'id' => $m->id,
            'category' => $m->category,
            'content' => $m->content,
            'recorded_at' => $m->recorded_at->toIso8601String(),
            'age_days' => (int) $m->recorded_at->diffInDays(now()),
        ])->all();
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'category' => $schema->string()->enum([
                'performance',
                'campaign',
                'seo',
                'conversion',
                'budget',
                'audience',
                'product',
                'general',
            ]),
            'limit' => $schema->integer(),
        ];
    }
}
