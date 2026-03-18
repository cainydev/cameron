<?php

declare(strict_types=1);

namespace App\Ai\Tools\Shopware;

use App\Ai\Attributes\Category;
use App\Enums\ToolCategory;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Stringable;

/**
 * Updates SEO and marketing content fields on a Shopware category.
 */
#[Category(ToolCategory::Shopware)]
class UpdateCategorySeoContent extends AbstractShopwareTool
{
    protected bool $isReadOnly = false;

    public function description(): Stringable|string
    {
        return 'Update the SEO content of a Shopware category: name, description (HTML), metaTitle, metaDescription, and keywords. Only provided fields are updated. Always call GetShopwareCategories first to read current values.';
    }

    public function label(array $arguments = []): string
    {
        return 'Update Category SEO Content';
    }

    /**
     * @param  array{category_id: string, name?: string, description?: string, meta_title?: string, meta_description?: string, keywords?: string}  $arguments
     */
    public function execute(array $arguments): string
    {
        $categoryId = $arguments['category_id']
            ?? throw new \RuntimeException('category_id is required.');

        $payload = ['id' => $categoryId];

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

        $this->shopwareApi()->update('category', [$payload]);

        return "Category {$categoryId} SEO content updated successfully.";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'category_id' => $schema->string()->description('The Shopware category ID (UUID).'),
            'name' => $schema->string()->description('Updated category name.'),
            'description' => $schema->string()->description('Category description (HTML allowed).'),
            'meta_title' => $schema->string()->description('SEO meta title (recommended max 60 characters).'),
            'meta_description' => $schema->string()->description('SEO meta description (recommended max 160 characters).'),
            'keywords' => $schema->string()->description('Comma-separated SEO keywords.'),
        ];
    }
}
