<?php

declare(strict_types=1);

namespace App\Enums;

enum AgentRole: string
{
    case Analytics = 'analytics';
    case Ads = 'ads';
    case Catalog = 'catalog';
    case System = 'system';

    /**
     * The tool categories available to this specialist role.
     *
     * @return list<ToolCategory>
     */
    public function toolCategories(): array
    {
        return match ($this) {
            self::Analytics => [ToolCategory::GoogleAnalytics, ToolCategory::SearchConsole, ToolCategory::AccountOverview, ToolCategory::Website],
            self::Ads => [ToolCategory::GoogleAds],
            self::Catalog => [ToolCategory::Shopware],
            self::System => [ToolCategory::System, ToolCategory::Memory],
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Analytics => 'Analytics Specialist',
            self::Ads => 'Ads Specialist',
            self::Catalog => 'Catalog Specialist',
            self::System => 'System',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Analytics => 'chart-bar',
            self::Ads => 'megaphone',
            self::Catalog => 'shopping-bag',
            self::System => 'cog-6-tooth',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Analytics => 'orange',
            self::Ads => 'blue',
            self::Catalog => 'indigo',
            self::System => 'zinc',
        };
    }
}
