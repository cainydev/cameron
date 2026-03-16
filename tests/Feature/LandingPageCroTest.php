<?php

use App\Ai\Tools\AnalyzeLandingPageCro;
use App\Enums\ToolCategory;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\Support\Facades\Http;

it('AnalyzeLandingPageCro is read-only with Website category', function () {
    $tool = new AnalyzeLandingPageCro;

    expect($tool->category())->toBe(ToolCategory::Website);

    $prop = (new ReflectionClass($tool))->getProperty('isReadOnly');
    expect($prop->getValue($tool))->toBeTrue();
});

it('AnalyzeLandingPageCro schema requires url and has optional adHeadline', function () {
    $schema = (new AnalyzeLandingPageCro)->schema(new JsonSchemaTypeFactory);

    expect($schema)->toHaveKey('url')->toHaveKey('adHeadline');
});

it('AnalyzeLandingPageCro label includes host when url provided', function () {
    $tool = new AnalyzeLandingPageCro;

    expect($tool->label(['url' => 'https://example.com/product']))->toBe('CRO Analysis: example.com');
});

it('AnalyzeLandingPageCro label falls back to generic when no url', function () {
    expect((new AnalyzeLandingPageCro)->label())->toBe('CRO Analysis');
});

it('AnalyzeLandingPageCro extracts cro signals from html', function () {
    $html = <<<'HTML'
        <!DOCTYPE html>
        <html>
        <head>
            <title>Bestes Lavendel Öl kaufen</title>
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <meta name="description" content="Hochwertiges Lavendel Öl">
            <link rel="canonical" href="https://example.com/lavendel">
        </head>
        <body>
            <h1>Lavendel Öl kaufen</h1>
            <form><input type="submit" value="Jetzt kaufen"></form>
            <button>In den Warenkorb</button>
        </body>
        </html>
    HTML;

    Http::fake([
        'https://example.com/lavendel' => Http::response($html, 200),
        'https://www.googleapis.com/*' => Http::response([], 200),
    ]);

    $result = (new AnalyzeLandingPageCro)->execute(['url' => 'https://example.com/lavendel']);

    expect($result['cro']['title'])->toBe('Bestes Lavendel Öl kaufen')
        ->and($result['cro']['h1'])->toBe('Lavendel Öl kaufen')
        ->and($result['cro']['hasViewportMeta'])->toBeTrue()
        ->and($result['cro']['hasForms'])->toBeTrue()
        ->and($result['cro']['ctaButtonCount'])->toBeGreaterThanOrEqual(1)
        ->and($result['cro']['canonicalUrl'])->toBe('https://example.com/lavendel')
        ->and($result['cro']['metaDescription'])->toBe('Hochwertiges Lavendel Öl');
});

it('AnalyzeLandingPageCro detects message mismatch between ad headline and h1', function () {
    $html = <<<'HTML'
        <!DOCTYPE html>
        <html>
        <head><title>Shop</title><meta name="viewport" content="width=device-width"></head>
        <body><h1>Willkommen in unserem Shop</h1></body>
        </html>
    HTML;

    Http::fake([
        'https://example.com/' => Http::response($html, 200),
        'https://www.googleapis.com/*' => Http::response([], 200),
    ]);

    $result = (new AnalyzeLandingPageCro)->execute([
        'url' => 'https://example.com/',
        'adHeadline' => 'Lavendel Öl günstig kaufen',
    ]);

    expect($result['cro']['messageMismatch'])->toBeTrue();
});

it('AnalyzeLandingPageCro detects no message mismatch when headlines overlap', function () {
    $html = <<<'HTML'
        <!DOCTYPE html>
        <html>
        <head><title>Shop</title><meta name="viewport" content="width=device-width"></head>
        <body><h1>Lavendel Öl günstig online kaufen</h1></body>
        </html>
    HTML;

    Http::fake([
        'https://example.com/' => Http::response($html, 200),
        'https://www.googleapis.com/*' => Http::response([], 200),
    ]);

    $result = (new AnalyzeLandingPageCro)->execute([
        'url' => 'https://example.com/',
        'adHeadline' => 'Lavendel Öl kaufen',
    ]);

    expect($result['cro']['messageMismatch'])->toBeFalse();
});

it('AnalyzeLandingPageCro throws on failed http request', function () {
    Http::fake([
        'https://example.com/' => Http::response('', 500),
    ]);

    expect(fn () => (new AnalyzeLandingPageCro)->execute(['url' => 'https://example.com/']))
        ->toThrow(RuntimeException::class);
});
