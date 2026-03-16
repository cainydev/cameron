<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\Attributes\Category;
use App\Enums\ToolCategory;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Stringable;

/**
 * Fetches the shop's homepage HTML and scans it for GA4 and GTM tracking snippets.
 */
#[Category(ToolCategory::GoogleAnalytics)]
class VerifyGa4TagPresence extends AbstractAgentTool
{
    protected bool $isReadOnly = true;

    /**
     * {@inheritDoc}
     */
    public function label(array $arguments = []): string
    {
        return 'Verify GA4 Tag';
    }

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return "Fetch the shop's homepage HTML and scan for GA4 (G-XXXXXXXX) and Google Tag Manager (GTM-XXXXXXX) snippets. Use this when GA4 reports zero sessions despite active ad spend, to confirm whether the tracking tag has been removed from the site.";
    }

    /**
     * Execute the tool's core business logic.
     *
     * @param  array<string, mixed>  $arguments
     * @return array{url: string, ga4_tags: string[], gtm_tags: string[], tracking_found: bool, error?: string}
     */
    public function execute(array $arguments): array
    {
        $url = $this->shop?->url
            ?? throw new \RuntimeException('Shop has no URL configured.');

        try {
            $response = Http::timeout(10)->get($url);
            $html = $response->body();

            preg_match_all('/G-[A-Z0-9]{6,12}/', $html, $ga4Matches);
            preg_match_all('/GTM-[A-Z0-9]{5,8}/', $html, $gtmMatches);

            $ga4Tags = array_values(array_unique($ga4Matches[0]));
            $gtmTags = array_values(array_unique($gtmMatches[0]));

            return [
                'url' => $url,
                'ga4_tags' => $ga4Tags,
                'gtm_tags' => $gtmTags,
                'tracking_found' => $ga4Tags !== [] || $gtmTags !== [],
            ];
        } catch (ConnectionException $e) {
            return [
                'url' => $url,
                'ga4_tags' => [],
                'gtm_tags' => [],
                'tracking_found' => false,
                'error' => $e->getMessage(),
            ];
        } catch (\Exception $e) {
            return [
                'url' => $url,
                'ga4_tags' => [],
                'gtm_tags' => [],
                'tracking_found' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
