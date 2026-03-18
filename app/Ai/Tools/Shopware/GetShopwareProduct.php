<?php

declare(strict_types=1);

namespace App\Ai\Tools\Shopware;

use App\Ai\Attributes\Category;
use App\Enums\ToolCategory;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Stringable;
use Vin\ShopwareSdk\Data\Criteria;

/**
 * Fetches full detail for a single Shopware product by ID.
 */
#[Category(ToolCategory::Shopware)]
class GetShopwareProduct extends AbstractShopwareTool
{
    protected bool $isReadOnly = true;

    public function description(): Stringable|string
    {
        return 'Fetch complete details for a single Shopware product by its ID. Returns all fields including name, description, metaTitle, metaDescription, keywords, customFields, tags, categories, properties, crossSellings, manufacturer, pricing, and variant information. Always call this before updating a product\'s content.';
    }

    public function label(array $arguments = []): string
    {
        return 'Shopware Product Detail';
    }

    /**
     * @param  array{product_id: string}  $arguments
     * @return array<string, mixed>
     */
    public function execute(array $arguments): array
    {
        $productId = $arguments['product_id']
            ?? throw new \RuntimeException('product_id is required.');

        $criteria = new Criteria;
        $criteria->addAssociation('categories');
        $criteria->addAssociation('manufacturer');
        $criteria->addAssociation('tags');
        $criteria->addAssociation('properties.group');
        $criteria->addAssociation('crossSellings.assignedProducts');
        $criteria->addAssociation('translations');
        $criteria->addAssociation('visibilities.salesChannel');

        $product = $this->shopwareApi()->get('product', $productId, $criteria);

        if ($product === null) {
            throw new \RuntimeException("Product {$productId} not found.");
        }

        $categoryNames = [];
        if ($product->categories) {
            foreach ($product->categories->getElements() as $cat) {
                $categoryNames[] = ['id' => $cat->id, 'name' => $cat->name];
            }
        }

        $tags = [];
        if ($product->tags) {
            foreach ($product->tags->getElements() as $tag) {
                $tags[] = ['id' => $tag->id, 'name' => $tag->name];
            }
        }

        $properties = [];
        if ($product->properties) {
            foreach ($product->properties->getElements() as $option) {
                $properties[] = [
                    'id' => $option->id,
                    'name' => $option->name,
                    'groupName' => $option->group?->name,
                ];
            }
        }

        $crossSellings = [];
        if ($product->crossSellings) {
            foreach ($product->crossSellings->getElements() as $cs) {
                $crossSellings[] = [
                    'id' => $cs->id,
                    'name' => $cs->name,
                    'type' => $cs->type,
                    'position' => $cs->position,
                ];
            }
        }

        $salesChannels = [];
        if ($product->visibilities) {
            foreach ($product->visibilities->getElements() as $vis) {
                $salesChannels[] = [
                    'id' => $vis->salesChannelId,
                    'name' => $vis->salesChannel?->name,
                    'visibility' => $vis->visibility,
                ];
            }
        }

        return [
            'id' => $product->id,
            'productNumber' => $product->productNumber,
            'name' => $product->name,
            'description' => $product->description,
            'metaTitle' => $product->metaTitle,
            'metaDescription' => $product->metaDescription,
            'keywords' => $product->keywords,
            'customFields' => $product->customFields,
            'active' => $product->active,
            'stock' => $product->stock,
            'price' => $product->price,
            'manufacturerName' => $product->manufacturer?->name,
            'manufacturerId' => $product->manufacturerId,
            'categories' => $categoryNames,
            'tags' => $tags,
            'properties' => $properties,
            'crossSellings' => $crossSellings,
            'salesChannels' => $salesChannels,
        ];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'product_id' => $schema->string()->description('The Shopware product ID (UUID).'),
        ];
    }
}
