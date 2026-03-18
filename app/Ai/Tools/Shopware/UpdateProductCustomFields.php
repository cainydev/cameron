<?php

declare(strict_types=1);

namespace App\Ai\Tools\Shopware;

use App\Ai\Attributes\Category;
use App\Enums\ToolCategory;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Stringable;

/**
 * Updates custom field values on a Shopware product.
 */
#[Category(ToolCategory::Shopware)]
class UpdateProductCustomFields extends AbstractShopwareTool
{
    protected bool $isReadOnly = false;

    public function description(): Stringable|string
    {
        return 'Update custom field values on a Shopware product. Provide a key-value map of field names to their new values. Custom field values are merged with existing ones — only provided fields are changed. Always call GetShopwareCustomFieldSets first to know available field names and types, and GetShopwareProduct to read current values.';
    }

    public function label(array $arguments = []): string
    {
        return 'Update Product Custom Fields';
    }

    /**
     * @param  array{product_id: string, custom_fields: array<string, mixed>}  $arguments
     */
    public function execute(array $arguments): string
    {
        $productId = $arguments['product_id']
            ?? throw new \RuntimeException('product_id is required.');

        $customFields = $arguments['custom_fields']
            ?? throw new \RuntimeException('custom_fields is required.');

        if (is_string($customFields)) {
            $customFields = json_decode($customFields, true)
                ?? throw new \RuntimeException('custom_fields must be valid JSON.');
        }

        if (! is_array($customFields) || $customFields === []) {
            throw new \RuntimeException('custom_fields must be a non-empty key-value object.');
        }

        $this->shopwareApi()->update('product', [[
            'id' => $productId,
            'customFields' => $customFields,
        ]]);

        return 'Product custom fields updated successfully ('.count($customFields).' field(s) set).';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'product_id' => $schema->string()->description('The Shopware product ID (UUID).'),
            'custom_fields' => $schema->string()->description('JSON object of custom field names to their new values (e.g. {"my_field":"value"}). Field names must match exactly as returned by GetShopwareCustomFieldSets.'),
        ];
    }
}
