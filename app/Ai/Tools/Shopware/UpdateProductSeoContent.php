<?php

declare(strict_types=1);

namespace App\Ai\Tools\Shopware;

use App\Ai\Attributes\Category;
use App\Enums\ToolCategory;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Stringable;

/**
 * Updates SEO and marketing content fields on a Shopware product.
 */
#[Category(ToolCategory::Shopware)]
class UpdateProductSeoContent extends AbstractShopwareTool
{
    protected bool $isReadOnly = false;

    public function description(): Stringable|string
    {
        return 'Update the SEO and marketing content of a Shopware product: name, description (HTML), metaTitle, metaDescription, and keywords. Only fields provided will be updated — omit any field to leave it unchanged. Always call GetShopwareProduct first to read the current values.';
    }

    public function label(array $arguments = []): string
    {
        return 'Update Product SEO Content';
    }

    /**
     * @param  array{product_id: string, name?: string, description?: string, meta_title?: string, meta_description?: string, keywords?: string}  $arguments
     */
    public function execute(array $arguments): string
    {
        $productId = $arguments['product_id']
            ?? throw new \RuntimeException('product_id is required.');

        $payload = ['id' => $productId];

        if (isset($arguments['name'])) {
            $payload['name'] = $arguments['name'];
        }

        if (isset($arguments['description'])) {
            $payload['description'] = $arguments['description'];
        }

        if (isset($arguments['meta_title'])) {
            $payload['metaTitle'] = $arguments['meta_title'];
        }

        if (isset($arguments['meta_description'])) {
            $payload['metaDescription'] = $arguments['meta_description'];
        }

        if (isset($arguments['keywords'])) {
            $payload['keywords'] = $arguments['keywords'];
        }

        if (count($payload) === 1) {
            return 'No fields to update were provided.';
        }

        $this->shopwareApi()->update('product', [$payload]);

        return "Product {$productId} SEO content updated successfully.";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'product_id' => $schema->string()->description('The Shopware product ID (UUID).'),
            'name' => $schema->string()->description('Updated product name.'),
            'description' => $schema->string()->description('Updated product description (HTML allowed).'),
            'meta_title' => $schema->string()->description('SEO meta title (recommended max 60 characters).'),
            'meta_description' => $schema->string()->description('SEO meta description (recommended max 160 characters).'),
            'keywords' => $schema->string()->description('Comma-separated SEO keywords.'),
        ];
    }
}
