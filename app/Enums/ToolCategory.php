<?php

declare(strict_types=1);

namespace App\Enums;

enum ToolCategory: string
{
    case GoogleAnalytics = 'google_analytics';
    case GoogleAds = 'google_ads';
    case SearchConsole = 'search_console';
    case Website = 'website';
    case AccountOverview = 'account_overview';
    case Goals = 'goals';
    case Memory = 'memory';
    case Shopware = 'shopware';
    case System = 'system';

    public function label(): string
    {
        return match ($this) {
            self::GoogleAnalytics => 'Google Analytics',
            self::GoogleAds => 'Google Ads',
            self::SearchConsole => 'Search Console',
            self::Website => 'Website',
            self::AccountOverview => 'Account Overview',
            self::Goals => 'Goals',
            self::Memory => 'Memory',
            self::Shopware => 'Shopware',
            self::System => 'System',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::GoogleAnalytics => 'chart-bar',
            self::GoogleAds => 'megaphone',
            self::SearchConsole => 'magnifying-glass',
            self::Website => 'globe-alt',
            self::AccountOverview => 'presentation-chart-bar',
            self::Goals => 'flag',
            self::Memory => 'light-bulb',
            self::Shopware => 'shopping-bag',
            self::System => 'cog-6-tooth',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::GoogleAnalytics => 'orange',
            self::GoogleAds => 'blue',
            self::SearchConsole => 'green',
            self::Website => 'cyan',
            self::AccountOverview => 'purple',
            self::Goals => 'amber',
            self::Memory => 'pink',
            self::Shopware => 'indigo',
            self::System => 'zinc',
        };
    }

    /**
     * Whether this category should be shown in the user-facing tool settings UI.
     */
    public function isUserConfigurable(): bool
    {
        return ! in_array($this, [self::Goals, self::Memory, self::System]);
    }

    /**
     * The Shop field that must be non-null for tools in this category to be available.
     */
    public function requiredShopField(): ?string
    {
        return match ($this) {
            self::GoogleAnalytics => 'ga4_property_id',
            self::GoogleAds => 'google_ads_customer_id',
            self::SearchConsole => 'search_console_url',
            self::Website => 'url',
            self::Shopware => 'shopware_url',
            self::AccountOverview, self::Goals, self::Memory, self::System => null,
        };
    }
}
