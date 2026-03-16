<?php

declare(strict_types=1);

namespace App\Ai\Concerns;

use App\Enums\ToolCategory;
use Illuminate\Support\Facades\RateLimiter;

trait RateLimitsGoogleApi
{
    /**
     * Check the API rate limit for the current tool's category and shop.
     *
     * @throws \RuntimeException when the rate limit is exceeded
     */
    protected function checkRateLimit(): void
    {
        $category = $this->category();

        if ($category === null || $this->shop === null) {
            return;
        }

        $config = $this->rateLimitConfig($category);

        if ($config === null) {
            return;
        }

        [$maxAttempts, $decaySeconds, $apiLabel] = $config;
        $key = "google_api:{$category->value}:{$this->shop->id}";

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $retryAfter = RateLimiter::availableIn($key);

            throw new \RuntimeException(
                "Rate limit exceeded for {$apiLabel}. Try again in {$retryAfter} seconds."
            );
        }

        RateLimiter::hit($key, $decaySeconds);
    }

    /**
     * @return array{0: int, 1: int, 2: string}|null
     */
    private function rateLimitConfig(ToolCategory $category): ?array
    {
        return match ($category) {
            ToolCategory::GoogleAnalytics => [10, 60, 'GA4'],
            ToolCategory::GoogleAds => [15, 60, 'Google Ads'],
            ToolCategory::SearchConsole => [10, 60, 'Search Console'],
            default => null,
        };
    }
}
