<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Shop;
use Illuminate\Support\Facades\Cache;
use Vin\ShopwareSdk\Client\AdminAuthenticator;
use Vin\ShopwareSdk\Client\GrantType\ClientCredentialsGrantType;
use Vin\ShopwareSdk\Data\AccessToken;
use Vin\ShopwareSdk\Data\Context;
use Vin\ShopwareSdk\Data\Criteria;
use Vin\ShopwareSdk\Repository\EntityRepository;

class ShopwareApiService
{
    private string $shopUrl;

    private string $clientId;

    private string $clientSecret;

    private string $shopCacheKey;

    public function __construct(Shop $shop)
    {
        $this->shopUrl = rtrim($shop->shopware_url ?? throw new \RuntimeException('Shop has no Shopware URL configured.'), '/');
        $this->clientId = $shop->shopware_client_id ?? throw new \RuntimeException('Shop has no Shopware client ID configured.');
        $this->clientSecret = $shop->shopware_client_secret ?? throw new \RuntimeException('Shop has no Shopware client secret configured.');
        $this->shopCacheKey = 'shop_'.($shop->id ?? md5($this->shopUrl));
    }

    public function context(): Context
    {
        return new Context($this->shopUrl, $this->accessToken());
    }

    /**
     * Build a repository for a given entity type.
     */
    public function repository(string $entityName): EntityRepository
    {
        return new EntityRepository($entityName);
    }

    /**
     * Execute a search and return raw results.
     *
     * @return array<string, mixed>
     */
    public function search(string $entityName, Criteria $criteria): mixed
    {
        return $this->repository($entityName)->search($criteria, $this->context());
    }

    /**
     * Get a single entity by ID.
     */
    public function get(string $entityName, string $id, Criteria $criteria): mixed
    {
        return $this->repository($entityName)->get($id, $criteria, $this->context());
    }

    /**
     * Create one or more entities.
     *
     * @param  list<array<string, mixed>>  $payload
     */
    public function create(string $entityName, array $payload): mixed
    {
        return $this->repository($entityName)->create($payload, $this->context());
    }

    /**
     * Update one or more entities.
     *
     * @param  list<array<string, mixed>>  $payload
     */
    public function update(string $entityName, array $payload): mixed
    {
        return $this->repository($entityName)->update($payload, $this->context());
    }

    /**
     * Fetch and cache an access token for this shop.
     */
    private function accessToken(): AccessToken
    {
        $cacheKey = "shopware_token:{$this->shopCacheKey}";

        $cached = Cache::get($cacheKey);

        if ($cached instanceof AccessToken && $cached->getExpirationTime() > time() + 30) {
            return $cached;
        }

        $grantType = new ClientCredentialsGrantType($this->clientId, $this->clientSecret);
        $authenticator = new AdminAuthenticator($grantType, $this->shopUrl);
        $token = $authenticator->fetchAccessToken();

        $ttl = max(0, $token->getExpirationTime() - time() - 30);
        Cache::put($cacheKey, $token, $ttl);

        return $token;
    }
}
