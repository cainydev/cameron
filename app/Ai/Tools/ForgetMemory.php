<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\Attributes\Category;
use App\Enums\ToolCategory;
use App\Models\CameronMemory;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Stringable;

/**
 * Deletes a Cameron memory entry that is no longer relevant.
 */
#[Category(ToolCategory::Memory)]
class ForgetMemory extends AbstractAgentTool
{
    protected bool $isReadOnly = false;

    protected bool $hidden = true;

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Delete a memory entry that is stale, incorrect, or no longer relevant. Use the id from RecallMemories. Prefer UpdateMemory if the finding is partially still true.';
    }

    /**
     * Execute the tool's core business logic.
     *
     * @param  array{id: int}  $arguments
     * @return array{success: bool}
     */
    public function execute(array $arguments): array
    {
        $shopId = $this->shop?->id
            ?? throw new \RuntimeException('No shop context set for ForgetMemory.');

        CameronMemory::query()
            ->where('id', $arguments['id'])
            ->where('shop_id', $shopId)
            ->delete();

        return ['success' => true];
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->required(),
        ];
    }
}
