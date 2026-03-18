<?php

declare(strict_types=1);

namespace App\Ai\Tools\Shopware;

use App\Ai\Tools\AbstractAgentTool;
use App\Services\ShopwareApiService;

abstract class AbstractShopwareTool extends AbstractAgentTool
{
    protected function shopwareApi(): ShopwareApiService
    {
        return new ShopwareApiService(
            $this->shop ?? throw new \RuntimeException('No shop context set on Shopware tool.')
        );
    }
}
