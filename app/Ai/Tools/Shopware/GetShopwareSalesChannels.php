<?php

declare(strict_types=1);

namespace App\Ai\Tools\Shopware;

use App\Ai\Attributes\Category;
use App\Enums\ToolCategory;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Stringable;
use Vin\ShopwareSdk\Data\Criteria;

/**
 * Fetches available Shopware sales channels.
 */
#[Category(ToolCategory::Shopware)]
class GetShopwareSalesChannels extends AbstractShopwareTool
{
    protected bool $isReadOnly = true;

    public function description(): Stringable|string
    {
        return 'Fetch all Shopware sales channels (id, name, type, currency, language, domain). Useful for understanding which channels products are visible in.';
    }

    public function label(array $arguments = []): string
    {
        return 'Shopware Sales Channels';
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<int, array<string, mixed>>
     */
    public function execute(array $arguments): array
    {
        $criteria = new Criteria;
        $criteria->setLimit(50);
        $criteria->addAssociation('type');
        $criteria->addAssociation('currency');
        $criteria->addAssociation('language');
        $criteria->addAssociation('domains');

        $result = $this->shopwareApi()->search('sales_channel', $criteria);

        $channels = [];

        foreach ($result->getElements() as $channel) {
            $domains = [];
            if ($channel->domains) {
                foreach ($channel->domains->getElements() as $domain) {
                    $domains[] = $domain->url;
                }
            }

            $channels[] = [
                'id' => $channel->id,
                'name' => $channel->name,
                'type' => $channel->type?->name,
                'currency' => $channel->currency?->isoCode,
                'language' => $channel->language?->name,
                'active' => $channel->active,
                'domains' => $domains,
            ];
        }

        return $channels;
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
