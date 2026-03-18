<?php

declare(strict_types=1);

namespace App\Ai\Tools\Shopware;

use App\Ai\Attributes\Category;
use App\Enums\ToolCategory;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Stringable;

/**
 * Replaces the tags on a Shopware product.
 */
#[Category(ToolCategory::Shopware)]
class UpdateProductTags extends AbstractShopwareTool
{
    protected bool $isReadOnly = false;

    public function description(): Stringable|string
    {
        return 'Replace all tags on a Shopware product. Provide a list of tag IDs — this will overwrite the current tags entirely. Use GetShopwareTags to find available tag IDs first.';
    }

    public function label(array $arguments = []): string
    {
        return 'Update Product Tags';
    }

    /**
     * @param  array{product_id: string, tag_ids: list<string>}  $arguments
     */
    public function execute(array $arguments): string
    {
        $productId = $arguments['product_id']
            ?? throw new \RuntimeException('product_id is required.');

        $rawTagIds = $arguments['tag_ids']
            ?? throw new \RuntimeException('tag_ids is required.');

        $tagIds = is_array($rawTagIds)
            ? $rawTagIds
            : array_map('trim', explode(',', (string) $rawTagIds));

        $tags = array_map(fn (string $id) => ['id' => $id], $tagIds);

        $this->shopwareApi()->update('product', [[
            'id' => $productId,
            'tags' => $tags,
        ]]);

        return 'Product tags updated successfully ('.count($tags).' tag(s) assigned).';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'product_id' => $schema->string()->description('The Shopware product ID (UUID).'),
            'tag_ids' => $schema->string()->description('Comma-separated tag IDs to assign (e.g. "id1,id2"). This replaces all existing tags.'),
        ];
    }
}
