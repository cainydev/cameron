<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\CameronMemory;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Stringable;

/**
 * Updates an existing Cameron memory entry — corrects outdated content or recategorises it.
 */
class UpdateMemory extends AbstractAgentTool
{
    protected bool $isReadOnly = false;

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Update an existing memory entry when new data contradicts or refines a prior finding. Provide the memory id from RecallMemories. The recorded_at timestamp will be refreshed to now.';
    }

    /**
     * Execute the tool's core business logic.
     *
     * @param  array{id: int, content: string, category?: string}  $arguments
     * @return array{success: bool, id: int, recorded_at: string}
     */
    public function execute(array $arguments): array
    {
        $shopId = $this->shop?->id
            ?? throw new \RuntimeException('No shop context set for UpdateMemory.');

        $memory = CameronMemory::query()
            ->where('id', $arguments['id'])
            ->where('shop_id', $shopId)
            ->firstOrFail();

        $memory->update([
            'content' => $arguments['content'],
            'category' => $arguments['category'] ?? $memory->category,
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
            'id' => $schema->integer()->required(),
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
            ]),
        ];
    }
}
