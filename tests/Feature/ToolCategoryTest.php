<?php

use App\Ai\Attributes\Category;
use App\Ai\Tools\AbstractAgentTool;
use App\Enums\ToolCategory;

it('every tool class has a Category attribute', function () {
    $toolsPath = app_path('Ai/Tools');
    $missing = [];

    foreach (glob($toolsPath.'/*.php') as $file) {
        $className = 'App\\Ai\\Tools\\'.basename($file, '.php');

        if (! class_exists($className)) {
            continue;
        }

        $ref = new ReflectionClass($className);

        if ($ref->isAbstract() || ! $ref->isSubclassOf(AbstractAgentTool::class)) {
            continue;
        }

        if ($ref->getAttributes(Category::class) === []) {
            $missing[] = $className;
        }
    }

    expect($missing)->toBeEmpty(
        'These tools are missing a #[Category] attribute: '.implode(', ', $missing)
    );
});

it('enum label returns a string for every case', function () {
    foreach (ToolCategory::cases() as $case) {
        expect($case->label())->toBeString()->not->toBeEmpty();
    }
});

it('enum icon returns a string for every case', function () {
    foreach (ToolCategory::cases() as $case) {
        expect($case->icon())->toBeString()->not->toBeEmpty();
    }
});

it('enum color returns a string for every case', function () {
    foreach (ToolCategory::cases() as $case) {
        expect($case->color())->toBeString()->not->toBeEmpty();
    }
});

it('enum requiredShopField returns null or a string for every case', function () {
    foreach (ToolCategory::cases() as $case) {
        $field = $case->requiredShopField();
        expect($field === null || is_string($field))->toBeTrue();
    }
});

it('GoogleAnalytics category requires ga4_property_id', function () {
    expect(ToolCategory::GoogleAnalytics->requiredShopField())->toBe('ga4_property_id');
});

it('GoogleAds category requires google_ads_customer_id', function () {
    expect(ToolCategory::GoogleAds->requiredShopField())->toBe('google_ads_customer_id');
});

it('SearchConsole category requires search_console_url', function () {
    expect(ToolCategory::SearchConsole->requiredShopField())->toBe('search_console_url');
});

it('Goals category has no required field', function () {
    expect(ToolCategory::Goals->requiredShopField())->toBeNull();
});

it('System category has no required field', function () {
    expect(ToolCategory::System->requiredShopField())->toBeNull();
});
