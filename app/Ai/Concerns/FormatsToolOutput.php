<?php

declare(strict_types=1);

namespace App\Ai\Concerns;

use Google\Ads\GoogleAds\V20\Enums\KeywordMatchTypeEnum\KeywordMatchType;

trait FormatsToolOutput
{
    protected function microsToCurrency(int|float $micros): float
    {
        return round($micros / 1_000_000, 2);
    }

    protected function toPercent(float $rate, int $decimals = 1): string
    {
        return round($rate * 100, $decimals).'%';
    }

    protected function toPosition(float $position): float
    {
        return round($position, 1);
    }

    protected function matchTypeLabel(int $matchType): string
    {
        return match ($matchType) {
            KeywordMatchType::EXACT => 'EXACT',
            KeywordMatchType::PHRASE => 'PHRASE',
            KeywordMatchType::BROAD => 'BROAD',
            default => 'UNKNOWN',
        };
    }

    protected function campaignStatusLabel(int $status): string
    {
        return match ($status) {
            2 => 'ENABLED',
            3 => 'PAUSED',
            4 => 'REMOVED',
            default => 'UNKNOWN',
        };
    }
}
