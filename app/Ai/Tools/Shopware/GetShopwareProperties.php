<?php

declare(strict_types=1);

namespace App\Ai\Tools\Shopware;

use App\Ai\Attributes\Category;
use App\Enums\ToolCategory;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Stringable;
use Vin\ShopwareSdk\Data\Criteria;

/**
 * Fetches Shopware property groups and their options.
 */
#[Category(ToolCategory::Shopware)]
class GetShopwareProperties extends AbstractShopwareTool
{
    protected bool $isReadOnly = true;

    public function description(): Stringable|string
    {
        return 'Fetch Shopware property groups (e.g. Colour, Size, Material) and their available options. Useful for writing accurate product descriptions that reference product attributes.';
    }

    public function label(array $arguments = []): string
    {
        return 'Shopware Properties';
    }

    /**
     * @param  array{limit?: int}  $arguments
     * @return array<int, array<string, mixed>>
     */
    public function execute(array $arguments): array
    {
        $criteria = new Criteria;
        $criteria->setLimit($arguments['limit'] ?? 100);
        $criteria->addAssociation('options');

        $result = $this->shopwareApi()->search('property_group', $criteria);

        $groups = [];

        foreach ($result->getElements() as $group) {
            $options = [];

            if ($group->options) {
                foreach ($group->options->getElements() as $option) {
                    $options[] = [
                        'id' => $option->id,
                        'name' => $option->name,
                        'position' => $option->position,
                    ];
                }
            }

            $groups[] = [
                'id' => $group->id,
                'name' => $group->name,
                'displayType' => $group->displayType,
                'sortingType' => $group->sortingType,
                'options' => $options,
            ];
        }

        return $groups;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'limit' => $schema->integer()->description('Number of property groups to return (default 100).'),
        ];
    }
}
