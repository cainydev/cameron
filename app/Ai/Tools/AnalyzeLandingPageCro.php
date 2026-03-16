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
 * Performs a CRO analysis of a landing page using HTML inspection and PageSpeed Insights.
 */
#[Category(ToolCategory::Website)]
class AnalyzeLandingPageCro extends AbstractAgentTool
{
    protected bool $isReadOnly = true;

    /**
     * {@inheritDoc}
     */
    public function label(array $arguments = []): string
    {
        if (! empty($arguments['url'])) {
            $host = parse_url($arguments['url'], PHP_URL_HOST) ?? $arguments['url'];

            return "CRO Analysis: {$host}";
        }

        return 'CRO Analysis';
    }

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Analyse a landing page for conversion rate optimisation (CRO) issues: CTA presence, mobile viewport, H1/title alignment, form detection, and PageSpeed performance score. Use to diagnose high bounce rates or low conversion rates.';
    }

    /**
     * @param  array{url: string, adHeadline?: string}  $arguments
     * @return array{url: string, pagespeed: array<string, mixed>|null, cro: array<string, mixed>}
     */
    public function execute(array $arguments): array
    {
        $url = $arguments['url'];
        $adHeadline = $arguments['adHeadline'] ?? null;

        $html = $this->fetchHtml($url);
        $cro = $this->analyzeHtml($html, $adHeadline);
        $pagespeed = $this->fetchPagespeed($url);

        return [
            'url' => $url,
            'pagespeed' => $pagespeed,
            'cro' => $cro,
        ];
    }

    /**
     * Fetch raw HTML from the URL.
     */
    private function fetchHtml(string $url): string
    {
        try {
            $response = Http::timeout(15)->get($url);
        } catch (ConnectionException $e) {
            throw new \RuntimeException("Failed to fetch page: {$e->getMessage()}");
        }

        if ($response->failed()) {
            throw new \RuntimeException("Failed to fetch page: HTTP {$response->status()}");
        }

        return $response->body();
    }

    /**
     * Extract CRO signals from the HTML.
     *
     * @return array{
     *     title: string|null,
     *     h1: string|null,
     *     hasViewportMeta: bool,
     *     hasForms: bool,
     *     ctaButtonCount: int,
     *     ctaTexts: list<string>,
     *     messageMismatch: bool|null,
     *     structuredDataTypes: list<string>,
     *     canonicalUrl: string|null,
     *     metaDescription: string|null
     * }
     */
    private function analyzeHtml(string $html, ?string $adHeadline): array
    {
        // Silence DOMDocument warnings for imperfect HTML.
        // Prepend the UTF-8 charset declaration so DOMDocument handles multibyte characters correctly.
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NOERROR);
        $xpath = new \DOMXPath($dom);

        $title = $this->extractText($xpath, '//title');
        $h1 = $this->extractText($xpath, '//h1');

        $hasViewportMeta = $xpath->query('//meta[@name="viewport"]')->length > 0;
        $hasForms = $xpath->query('//form')->length > 0;

        $ctaTexts = $this->extractCtaTexts($xpath);
        $structuredDataTypes = $this->extractStructuredDataTypes($html);
        $canonicalUrl = $this->extractAttribute($xpath, '//link[@rel="canonical"]', 'href');
        $metaDescription = $this->extractAttribute($xpath, '//meta[@name="description"]', 'content');

        $messageMismatch = null;
        if ($adHeadline !== null && $h1 !== null) {
            $messageMismatch = ! $this->headlinesOverlap($adHeadline, $h1);
        }

        return [
            'title' => $title,
            'h1' => $h1,
            'hasViewportMeta' => $hasViewportMeta,
            'hasForms' => $hasForms,
            'ctaButtonCount' => count($ctaTexts),
            'ctaTexts' => $ctaTexts,
            'messageMismatch' => $messageMismatch,
            'structuredDataTypes' => $structuredDataTypes,
            'canonicalUrl' => $canonicalUrl,
            'metaDescription' => $metaDescription,
        ];
    }

    /**
     * Query Google PageSpeed Insights API (mobile strategy) for performance score.
     *
     * @return array{performanceScore: int, fcp: string|null, lcp: string|null, tbt: string|null, cls: string|null}|null
     */
    private function fetchPagespeed(string $url): ?array
    {
        $apiKey = config('google.pagespeed_api_key');

        $params = ['url' => $url, 'strategy' => 'mobile'];
        if ($apiKey) {
            $params['key'] = $apiKey;
        }

        try {
            $response = Http::timeout(20)->get('https://www.googleapis.com/pagespeedonline/v5/runPagespeed', $params);
        } catch (ConnectionException) {
            return null;
        }

        if ($response->failed()) {
            return null;
        }

        $data = $response->json();
        $categories = $data['lighthouseResult']['categories'] ?? [];
        $audits = $data['lighthouseResult']['audits'] ?? [];

        return [
            'performanceScore' => (int) round(($categories['performance']['score'] ?? 0) * 100),
            'fcp' => $audits['first-contentful-paint']['displayValue'] ?? null,
            'lcp' => $audits['largest-contentful-paint']['displayValue'] ?? null,
            'tbt' => $audits['total-blocking-time']['displayValue'] ?? null,
            'cls' => $audits['cumulative-layout-shift']['displayValue'] ?? null,
        ];
    }

    /**
     * Extract text content from the first matching XPath node.
     */
    private function extractText(\DOMXPath $xpath, string $query): ?string
    {
        $nodes = $xpath->query($query);

        if ($nodes === false || $nodes->length === 0) {
            return null;
        }

        $text = trim($nodes->item(0)->textContent);

        return $text !== '' ? $text : null;
    }

    /**
     * Extract an attribute from the first matching XPath node.
     */
    private function extractAttribute(\DOMXPath $xpath, string $query, string $attribute): ?string
    {
        $nodes = $xpath->query($query);

        if ($nodes === false || $nodes->length === 0) {
            return null;
        }

        $value = $nodes->item(0)->attributes?->getNamedItem($attribute)?->nodeValue;

        return ($value !== null && $value !== '') ? $value : null;
    }

    /**
     * Extract visible CTA button texts (button elements and input[type=submit]).
     *
     * @return list<string>
     */
    private function extractCtaTexts(\DOMXPath $xpath): array
    {
        $texts = [];

        $buttons = $xpath->query('//button | //input[@type="submit"] | //a[contains(@class,"btn") or contains(@class,"button")]');

        if ($buttons === false) {
            return $texts;
        }

        foreach ($buttons as $node) {
            $text = trim($node->textContent);
            if ($text !== '') {
                $texts[] = $text;
            }
        }

        return array_values(array_unique($texts));
    }

    /**
     * Extract @type values from JSON-LD structured data blocks.
     *
     * @return list<string>
     */
    private function extractStructuredDataTypes(string $html): array
    {
        $types = [];

        preg_match_all('/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html, $matches);

        foreach ($matches[1] as $json) {
            $data = json_decode($json, true);
            if (is_array($data) && isset($data['@type'])) {
                $types[] = $data['@type'];
            }
        }

        return array_values(array_unique($types));
    }

    /**
     * Check whether the ad headline and H1 share at least one significant word.
     */
    private function headlinesOverlap(string $adHeadline, string $h1): bool
    {
        $stopWords = ['der', 'die', 'das', 'und', 'für', 'the', 'and', 'for', 'with', 'mit', 'in', 'von', 'zu'];

        $adWords = array_diff(
            array_map('mb_strtolower', preg_split('/\W+/u', $adHeadline, -1, PREG_SPLIT_NO_EMPTY) ?: []),
            $stopWords
        );

        $h1Words = array_map('mb_strtolower', preg_split('/\W+/u', $h1, -1, PREG_SPLIT_NO_EMPTY) ?: []);

        return array_intersect($adWords, $h1Words) !== [];
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'url' => $schema->string()->required(),
            'adHeadline' => $schema->string(),
        ];
    }
}
