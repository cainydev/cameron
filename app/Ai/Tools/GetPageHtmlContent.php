<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Stringable;

/**
 * Fetches and cleans the HTML content of a web page for SEO analysis.
 */
class GetPageHtmlContent extends AbstractAgentTool
{
    protected bool $isReadOnly = true;

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Fetch and return the visible text content of a web page. Scripts, styles and SVGs are stripped to save tokens. Use for SEO content analysis.';
    }

    /**
     * Execute the tool's core business logic.
     *
     * @param  array{url: string}  $arguments
     * @return array{url: string, content: string}
     */
    public function execute(array $arguments): array
    {
        $url = $arguments['url'];

        try {
            $response = Http::timeout(15)->get($url);
        } catch (ConnectionException $e) {
            throw new \RuntimeException("Failed to fetch page: {$e->getMessage()}");
        }

        if ($response->failed()) {
            throw new \RuntimeException("Failed to fetch page: HTTP {$response->status()}");
        }

        $html = $response->body();

        $cleaned = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html) ?? $html;
        $cleaned = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $cleaned) ?? $cleaned;
        $cleaned = preg_replace('/<svg\b[^>]*>.*?<\/svg>/is', '', $cleaned) ?? $cleaned;

        return [
            'url' => $url,
            'content' => $cleaned,
        ];
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'url' => $schema->string()->required(),
        ];
    }
}
