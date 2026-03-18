<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ShopFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shop extends Model
{
    /** @use HasFactory<ShopFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'url',
        'timezone',
        'currency',
        'ga4_property_id',
        'google_ads_customer_id',
        'search_console_url',
        'google_refresh_token',
        'merchant_center_id',
        'base_instructions',
        'brand_guidelines',
        'target_roas',
        'shopware_url',
        'shopware_client_id',
        'shopware_client_secret',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'google_refresh_token',
        'shopware_client_secret',
    ];

    /**
     * Determine whether the shop has a connected Google account.
     */
    public function hasGoogleConnected(): bool
    {
        return ! empty($this->google_refresh_token);
    }

    public function hasShopwareConnected(): bool
    {
        return ! empty($this->shopware_url)
            && ! empty($this->shopware_client_id)
            && ! empty($this->shopware_client_secret);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<AgentGoal, $this>
     */
    public function goals(): HasMany
    {
        return $this->hasMany(AgentGoal::class);
    }

    /**
     * @return HasMany<ShopToolSetting, $this>
     */
    public function toolSettings(): HasMany
    {
        return $this->hasMany(ShopToolSetting::class);
    }
}
