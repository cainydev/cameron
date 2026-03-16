<?php

declare(strict_types=1);

namespace App\Ai;

use App\Ai\Attributes\Category;
use App\Ai\Tools\AbstractAgentTool;
use App\Enums\ToolCategory;
use App\Models\Shop;
use App\Models\ShopToolSetting;
use ReflectionClass;

class ToolRegistry
{
    /**
     * Cached list of discovered tool class names.
     *
     * @var list<class-string<AbstractAgentTool>>|null
     */
    protected static ?array $discoveredClasses = null;

    protected ?Shop $shop = null;

    protected ?int $taskId = null;

    /** @var list<ToolCategory>|null */
    protected ?array $includeCategories = null;

    /** @var list<ToolCategory> */
    protected array $excludedCategories = [];

    protected bool $excludeApproval = false;

    public function forShop(Shop $shop): static
    {
        $instance = clone $this;
        $instance->shop = $shop;

        return $instance;
    }

    public function forTask(int $taskId): static
    {
        $instance = clone $this;
        $instance->taskId = $taskId;

        return $instance;
    }

    /**
     * @param  list<ToolCategory>  $categories
     */
    public function inCategories(array $categories): static
    {
        $instance = clone $this;
        $instance->includeCategories = $categories;

        return $instance;
    }

    /**
     * @param  list<ToolCategory>  $categories
     */
    public function excludeCategories(array $categories): static
    {
        $instance = clone $this;
        $instance->excludedCategories = $categories;

        return $instance;
    }

    public function excludeApprovalRequired(): static
    {
        $instance = clone $this;
        $instance->excludeApproval = true;

        return $instance;
    }

    /**
     * Resolve tools based on the configured filters.
     *
     * @return list<AbstractAgentTool>
     */
    public function resolve(): array
    {
        $classes = static::discoverToolClasses();
        $settings = $this->loadSettings();

        $tools = [];

        foreach ($classes as $class) {
            $category = $this->resolveCategory($class);

            // Filter by included categories
            if ($this->includeCategories !== null && ($category === null || ! in_array($category, $this->includeCategories, true))) {
                continue;
            }

            // Filter by excluded categories
            if ($category !== null && in_array($category, $this->excludedCategories, true)) {
                continue;
            }

            /** @var AbstractAgentTool $tool */
            $tool = new $class;

            // Filter by shop field availability
            if ($this->shop !== null) {
                $requiredFields = $tool->requiredShopFields();

                $missingField = false;
                foreach ($requiredFields as $field) {
                    if (empty($this->shop->{$field})) {
                        $missingField = true;
                        break;
                    }
                }

                if ($missingField) {
                    continue;
                }
            }

            // Filter by shop tool settings
            if ($category !== null && isset($settings[$category->value])) {
                $setting = $settings[$category->value];

                if (! $setting->is_enabled) {
                    continue;
                }
            }

            // Set context
            if ($this->shop !== null) {
                $tool->forShop($this->shop);
            }

            if ($this->taskId !== null) {
                $tool->forTask($this->taskId);
            }

            // Apply approval overrides from settings
            if ($category !== null && isset($settings[$category->value])) {
                $setting = $settings[$category->value];

                match ($setting->approval_mode) {
                    'auto' => $tool->setRequiresApproval(false),
                    'require_approval' => $tool->setRequiresApproval(true),
                    default => null,
                };

                // Apply per-tool overrides
                if (is_array($setting->tool_overrides)) {
                    $shortName = (new ReflectionClass($tool))->getShortName();

                    if (isset($setting->tool_overrides[$shortName])) {
                        $override = $setting->tool_overrides[$shortName];

                        if (isset($override['requires_approval'])) {
                            $tool->setRequiresApproval((bool) $override['requires_approval']);
                        }

                        if (isset($override['is_enabled']) && ! $override['is_enabled']) {
                            continue;
                        }
                    }
                }
            }

            // Filter out approval-required tools if requested
            if ($this->excludeApproval) {
                $ref = new ReflectionClass($tool);
                $prop = $ref->getProperty('requiresApproval');

                if ($prop->getValue($tool)) {
                    continue;
                }
            }

            $tools[] = $tool;
        }

        return $tools;
    }

    /**
     * Discover all tool classes in the Tools directory.
     *
     * @return list<class-string<AbstractAgentTool>>
     */
    public static function discoverToolClasses(): array
    {
        if (static::$discoveredClasses !== null) {
            return static::$discoveredClasses;
        }

        $toolsPath = app_path('Ai/Tools');
        $classes = [];

        foreach (glob($toolsPath.'/*.php') as $file) {
            $className = 'App\\Ai\\Tools\\'.basename($file, '.php');

            if (! class_exists($className)) {
                continue;
            }

            $ref = new ReflectionClass($className);

            if ($ref->isAbstract() || ! $ref->isSubclassOf(AbstractAgentTool::class)) {
                continue;
            }

            // Only include tools that have a Category attribute
            if ($ref->getAttributes(Category::class) === []) {
                continue;
            }

            $classes[] = $className;
        }

        static::$discoveredClasses = $classes;

        return $classes;
    }

    /**
     * Resolve the ToolCategory from a class's attribute.
     */
    protected function resolveCategory(string $class): ?ToolCategory
    {
        $ref = new ReflectionClass($class);
        $attrs = $ref->getAttributes(Category::class);

        if ($attrs === []) {
            return null;
        }

        return $attrs[0]->newInstance()->category;
    }

    /**
     * Load shop tool settings keyed by category value.
     *
     * @return array<string, ShopToolSetting>
     */
    protected function loadSettings(): array
    {
        if ($this->shop === null) {
            return [];
        }

        return $this->shop->toolSettings
            ->keyBy(fn (ShopToolSetting $s) => $s->category->value)
            ->all();
    }

    /**
     * Clear the discovered classes cache (useful for testing).
     */
    public static function clearCache(): void
    {
        static::$discoveredClasses = null;
    }
}
