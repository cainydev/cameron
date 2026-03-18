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
 * Fetches Shopware custom field sets and their fields.
 */
#[Category(ToolCategory::Shopware)]
class GetShopwareCustomFieldSets extends AbstractShopwareTool
{
    protected bool $isReadOnly = true;

    public function description(): Stringable|string
    {
        return 'Fetch all Shopware custom field sets with their individual field definitions (name, type, label, config). Always call this before using UpdateProductCustomFields so you know which field names and types are available.';
    }

    public function label(array $arguments = []): string
    {
        return 'Shopware Custom Field Sets';
    }

    /**
     * @param  array{entity?: string}  $arguments
     * @return array<int, array<string, mixed>>
     */
    public function execute(array $arguments): array
    {
        $criteria = new Criteria;
        $criteria->setLimit(100);
        $criteria->addAssociation('customFields');

        if (! empty($arguments['entity'])) {
            $criteria->addFilter(new EqualsFilter('relations.entityName', $arguments['entity']));
        }

        $result = $this->shopwareApi()->search('custom_field_set', $criteria);

        $sets = [];

        foreach ($result->getElements() as $set) {
            $fields = [];

            if ($set->customFields) {
                foreach ($set->customFields->getElements() as $field) {
                    $fields[] = [
                        'name' => $field->name,
                        'type' => $field->type,
                        'label' => $field->config['label'] ?? null,
                        'helpText' => $field->config['helpText'] ?? null,
                        'componentName' => $field->config['componentName'] ?? null,
                        'options' => $field->config['options'] ?? null,
                    ];
                }
            }

            $sets[] = [
                'id' => $set->id,
                'name' => $set->name,
                'active' => $set->active,
                'fields' => $fields,
            ];
        }

        return $sets;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'entity' => $schema->string()->description('Filter sets by entity name, e.g. "product" or "category". Omit to return all.'),
        ];
    }
}
