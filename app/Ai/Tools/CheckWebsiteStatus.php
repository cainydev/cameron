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
 * Checks whether the shop's website is reachable and returns the HTTP status code and response time.
 */
#[Category(ToolCategory::Website)]
class CheckWebsiteStatus extends AbstractAgentTool
{
    protected bool $isReadOnly = true;

    /**
     * {@inheritDoc}
     */
    public function label(array $arguments = []): string
    {
        return 'Check Website Status';
    }

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return "Check whether the shop's website is reachable. Returns the HTTP status code and response time in ms. Use this when traffic anomalies suggest the site may be down.";
    }

    /**
     * Execute the tool's core business logic.
     *
     * @param  array<string, mixed>  $arguments
     * @return array{url: string, status_code: int, response_time_ms: int|null, reachable: bool, error?: string}
     */
    public function execute(array $arguments): array
    {
        $url = $this->shop?->url
            ?? throw new \RuntimeException('Shop has no URL configured.');

        try {
            $start = microtime(true);
            $response = Http::timeout(10)->get($url);
            $elapsed = microtime(true) - $start;

            return [
                'url' => $url,
                'status_code' => $response->status(),
                'response_time_ms' => (int) ($elapsed * 1000),
                'reachable' => $response->successful(),
            ];
        } catch (ConnectionException $e) {
            return [
                'url' => $url,
                'status_code' => 0,
                'response_time_ms' => null,
                'reachable' => false,
                'error' => $e->getMessage(),
            ];
        } catch (\Exception $e) {
            return [
                'url' => $url,
                'status_code' => 0,
                'response_time_ms' => null,
                'reachable' => false,
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
