<?php

declare(strict_types=1);

namespace App\Ai\Tools\Shopware;

use App\Ai\Attributes\Category;
use App\Enums\ToolCategory;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Stringable;

/**
 * Creates or replaces a cross-selling assignment on a Shopware product.
 */
#[Category(ToolCategory::Shopware)]
class UpdateProductCrossSellings extends AbstractShopwareTool
{
    protected bool $isReadOnly = false;

    public function description(): Stringable|string
    {
        return 'Create or update a cross-selling (upsell/cross-sell) group on a Shopware product. Each cross-selling group has a name and a list of product IDs to recommend. Use GetShopwareProduct to see existing cross-selling groups before making changes.';
    }

    public function label(array $arguments = []): string
    {
        return 'Update Product Cross-Sellings';
    }

    /**
     * @param  array{product_id: string, name: string, product_ids: list<string>, cross_selling_id?: string, position?: int}  $arguments
     */
    public function execute(array $arguments): string
    {
        $productId = $arguments['product_id']
            ?? throw new \RuntimeException('product_id is required.');

        $name = $arguments['name']
            ?? throw new \RuntimeException('name is required.');

        $rawProductIds = $arguments['product_ids']
            ?? throw new \RuntimeException('product_ids is required.');

        $productIds = is_array($rawProductIds)
            ? $rawProductIds
            : array_map('trim', explode(',', (string) $rawProductIds));

        $assignedProducts = array_map(
            fn (string $id, int $pos) => ['productId' => $id, 'position' => $pos + 1],
            $productIds,
            array_keys($productIds)
        );

        $crossSelling = [
            'productId' => $productId,
            'name' => $name,
            'type' => 'productList',
            'active' => true,
            'position' => $arguments['position'] ?? 1,
            'assignedProducts' => $assignedProducts,
        ];

        if (! empty($arguments['cross_selling_id'])) {
            $crossSelling['id'] = $arguments['cross_selling_id'];
            $this->shopwareApi()->update('product_cross_selling', [$crossSelling]);
        } else {
            $this->shopwareApi()->create('product_cross_selling', [$crossSelling]);
        }

        return "Cross-selling group \"{$name}\" saved with ".count($productIds).' product(s).';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'product_id' => $schema->string()->description('The Shopware product ID to attach cross-sellings to.'),
            'name' => $schema->string()->description('Display name for the cross-selling group (e.g. "You might also like").'),
            'product_ids' => $schema->string()->description('Comma-separated product IDs to recommend, in display order.'),
            'cross_selling_id' => $schema->string()->description('Existing cross-selling ID to update. Omit to create a new group.'),
            'position' => $schema->integer()->description('Display position of this group (default 1).'),
        ];
    }
}
