<?php

declare(strict_types=1);

namespace App\Ai\Tools\Shopware;

use App\Ai\Attributes\Category;
use App\Enums\ToolCategory;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Stringable;
use Vin\ShopwareSdk\Data\Criteria;
use Vin\ShopwareSdk\Data\Filter\ContainsFilter;

/**
 * Fetches available Shopware tags for use in product assignments.
 */
#[Category(ToolCategory::Shopware)]
class GetShopwareTags extends AbstractShopwareTool
{
    protected bool $isReadOnly = true;

    public function description(): Stringable|string
    {
        return 'Fetch existing Shopware tags (id and name). Use this to find tag IDs before assigning them to products with UpdateProductTags.';
    }

    public function label(array $arguments = []): string
    {
        return 'Shopware Tags';
    }

    /**
     * @param  array{search?: string, limit?: int}  $arguments
     * @return array<int, array{id: string, name: string}>
     */
    public function execute(array $arguments): array
    {
        $criteria = new Criteria;
        $criteria->setLimit($arguments['limit'] ?? 200);

        if (! empty($arguments['search'])) {
            $criteria->addFilter(new ContainsFilter('name', $arguments['search']));
        }

        $result = $this->shopwareApi()->search('tag', $criteria);

        $tags = [];

        foreach ($result->getElements() as $tag) {
            $tags[] = [
                'id' => $tag->id,
                'name' => $tag->name,
            ];
        }

        return $tags;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()->description('Filter tags by name (partial match).'),
            'limit' => $schema->integer()->description('Max tags to return (default 200).'),
        ];
    }
}
