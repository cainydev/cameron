<?php

declare(strict_types=1);

namespace App\Ai\Tools\Shopware;

use App\Ai\Attributes\Category;
use App\Enums\ToolCategory;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Stringable;
use Vin\ShopwareSdk\Data\Criteria;
use Vin\ShopwareSdk\Data\Filter\EqualsFilter;

/**
 * Fetches Shopware categories with their tree structure.
 */
#[Category(ToolCategory::Shopware)]
class GetShopwareCategories extends AbstractShopwareTool
{
    protected bool $isReadOnly = true;

    public function description(): Stringable|string
    {
        return 'Fetch Shopware product categories. Returns id, name, parentId, level, active status, and SEO meta fields. Use this to understand the category structure before fetching or updating products.';
    }

    public function label(array $arguments = []): string
    {
        return 'Shopware Categories';
    }

    /**
     * @param  array{parent_id?: string|null, limit?: int, page?: int}  $arguments
     * @return array<int, array<string, mixed>>
     */
    public function execute(array $arguments): array
    {
        $criteria = new Criteria;
        $criteria->setLimit($arguments['limit'] ?? 100);
        $criteria->setPage($arguments['page'] ?? 1);
        $criteria->addAssociation('translations');

        if (! empty($arguments['parent_id'])) {
            $criteria->addFilter(new EqualsFilter('parentId', $arguments['parent_id']));
        }

        $result = $this->shopwareApi()->search('category', $criteria);

        $categories = [];

        foreach ($result->getElements() as $category) {
            $categories[] = [
                'id' => $category->id,
                'name' => $category->name,
                'parentId' => $category->parentId,
                'level' => $category->level,
                'active' => $category->active,
                'type' => $category->type,
                'metaTitle' => $category->metaTitle,
                'metaDescription' => $category->metaDescription,
                'keywords' => $category->keywords,
                'description' => $category->description,
            ];
        }

        return $categories;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'parent_id' => $schema->string()->description('Filter by parent category ID. Omit to fetch top-level categories.'),
            'limit' => $schema->integer()->description('Number of categories to return (default 100).'),
            'page' => $schema->integer()->description('Page number for pagination (default 1).'),
        ];
    }
}
