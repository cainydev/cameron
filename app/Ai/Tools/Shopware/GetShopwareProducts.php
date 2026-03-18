<?php

declare(strict_types=1);

namespace App\Ai\Tools\Shopware;

use App\Ai\Attributes\Category;
use App\Enums\ToolCategory;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Stringable;
use Vin\ShopwareSdk\Data\Criteria;
use Vin\ShopwareSdk\Data\Filter\ContainsFilter;
use Vin\ShopwareSdk\Data\Filter\EqualsFilter;
use Vin\ShopwareSdk\Data\Filter\MultiFilter;

/**
 * Searches Shopware products with filtering and pagination.
 */
#[Category(ToolCategory::Shopware)]
class GetShopwareProducts extends AbstractShopwareTool
{
    protected bool $isReadOnly = true;

    public function description(): Stringable|string
    {
        return 'Search and list Shopware products. Returns product id, product number, name, active status, stock, price, manufacturer, category assignments, and SEO/meta fields. Supports filtering by name, category, or active state. Use GetShopwareProduct for full detail on a single product.';
    }

    public function label(array $arguments = []): string
    {
        return 'Shopware Products';
    }

    /**
     * @param  array{search?: string, category_id?: string, active?: bool, limit?: int, page?: int}  $arguments
     * @return array<int, array<string, mixed>>
     */
    public function execute(array $arguments): array
    {
        $criteria = new Criteria;
        $criteria->setLimit($arguments['limit'] ?? 50);
        $criteria->setPage($arguments['page'] ?? 1);
        $criteria->addAssociation('categories');
        $criteria->addAssociation('manufacturer');
        $criteria->addAssociation('tags');

        $filters = [];

        if (! empty($arguments['search'])) {
            $filters[] = new ContainsFilter('name', $arguments['search']);
        }

        if (! empty($arguments['category_id'])) {
            $filters[] = new EqualsFilter('categories.id', $arguments['category_id']);
        }

        if (isset($arguments['active'])) {
            $filters[] = new EqualsFilter('active', $arguments['active']);
        }

        // Only top-level products (not variants)
        $filters[] = new EqualsFilter('parentId', null);

        if (count($filters) > 1) {
            $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_AND, $filters));
        } elseif (count($filters) === 1) {
            $criteria->addFilter($filters[0]);
        }

        $result = $this->shopwareApi()->search('product', $criteria);

        $products = [];

        foreach ($result->getElements() as $product) {
            $categoryNames = [];
            if ($product->categories) {
                foreach ($product->categories->getElements() as $cat) {
                    $categoryNames[] = ['id' => $cat->id, 'name' => $cat->name];
                }
            }

            $products[] = [
                'id' => $product->id,
                'productNumber' => $product->productNumber,
                'name' => $product->name,
                'active' => $product->active,
                'stock' => $product->stock,
                'price' => $product->price,
                'manufacturerName' => $product->manufacturer?->name,
                'categories' => $categoryNames,
                'metaTitle' => $product->metaTitle,
                'metaDescription' => $product->metaDescription,
                'keywords' => $product->keywords,
                'description' => mb_strimwidth($product->description ?? '', 0, 200, '…'),
            ];
        }

        return $products;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()->description('Filter products by name (partial match).'),
            'category_id' => $schema->string()->description('Filter products by category ID.'),
            'active' => $schema->boolean()->description('Filter by active status. Omit to return all.'),
            'limit' => $schema->integer()->description('Number of products per page (default 50).'),
            'page' => $schema->integer()->description('Page number (default 1).'),
        ];
    }
}
