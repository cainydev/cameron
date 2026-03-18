<?php

declare(strict_types=1);

namespace App\Ai\Tools\Shopware;

use App\Ai\Attributes\Category;
use App\Enums\ToolCategory;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Stringable;
use Vin\ShopwareSdk\Data\Criteria;

/**
 * Fetches available Shopware languages and locales.
 */
#[Category(ToolCategory::Shopware)]
class GetShopwareLanguages extends AbstractShopwareTool
{
    protected bool $isReadOnly = true;

    public function description(): Stringable|string
    {
        return 'Fetch all configured Shopware languages with their locale codes (e.g. en-GB, de-DE). Use this to know which languages content translations are available in.';
    }

    public function label(array $arguments = []): string
    {
        return 'Shopware Languages';
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<int, array{id: string, name: string, locale: string}>
     */
    public function execute(array $arguments): array
    {
        $criteria = new Criteria;
        $criteria->setLimit(50);
        $criteria->addAssociation('locale');

        $result = $this->shopwareApi()->search('language', $criteria);

        $languages = [];

        foreach ($result->getElements() as $lang) {
            $languages[] = [
                'id' => $lang->id,
                'name' => $lang->name,
                'locale' => $lang->locale?->code,
            ];
        }

        return $languages;
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
