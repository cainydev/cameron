<?php

use App\Ai\ToolRegistry;
use App\Enums\ToolCategory;
use App\Models\Shop;
use App\Models\ShopToolSetting;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Tool Settings')] class extends Component
{
    #[Locked]
    public ?int $shopId = null;

    /** @var array<string, bool> */
    public array $toolEnabled = [];

    /** @var array<string, string> */
    public array $toolApprovalModes = [];

    public function mount(): void
    {
        $shop = Auth::user()->shops()->first();

        if (! $shop) {
            $this->redirect(route('shop.edit'), navigate: true);

            return;
        }

        $this->shopId = $shop->id;
        $this->loadToolSettings($shop);
    }

    private function loadToolSettings(Shop $shop): void
    {
        $settings = $shop->toolSettings->keyBy(fn (ShopToolSetting $s) => $s->category->value);

        foreach (array_filter(ToolCategory::cases(), fn (ToolCategory $c) => $c->isUserConfigurable()) as $category) {
            $setting = $settings->get($category->value);
            $this->toolEnabled[$category->value] = $setting?->is_enabled ?? true;
            $this->toolApprovalModes[$category->value] = $setting?->approval_mode ?? 'default';
        }
    }

    public function updateToolCategory(string $categoryValue): void
    {
        if (! $this->shopId) {
            return;
        }

        ShopToolSetting::query()->updateOrCreate(
            ['shop_id' => $this->shopId, 'category' => ToolCategory::from($categoryValue)],
            [
                'is_enabled' => $this->toolEnabled[$categoryValue] ?? true,
                'approval_mode' => $this->toolApprovalModes[$categoryValue] ?? 'default',
            ],
        );

        ToolRegistry::clearCache();
    }

    #[Computed]
    public function toolCategories(): array
    {
        $shop = $this->shopId ? Shop::find($this->shopId) : null;
        $categories = [];

        foreach (array_filter(ToolCategory::cases(), fn (ToolCategory $c) => $c->isUserConfigurable()) as $category) {
            $requiredField = $category->requiredShopField();
            $isConnected = $requiredField === null || ($shop && ! empty($shop->{$requiredField}));

            $categories[] = [
                'value' => $category->value,
                'label' => $category->label(),
                'icon' => $category->icon(),
                'color' => $category->color(),
                'requiredField' => $requiredField,
                'isConnected' => $isConnected,
            ];
        }

        return $categories;
    }
}; ?>

<section class="w-full">
    <div class="relative mb-6 w-full">
        <div class="px-6 pt-6">
            <flux:heading size="xl" level="1">Shop Settings</flux:heading>
            <flux:subheading size="lg" class="mb-6">Configure your shop details and integrations.</flux:subheading>
        </div>
        <flux:separator variant="subtle" />
    </div>

    <x-pages::shop.settings-layout heading="Tools" subheading="Control which tool categories Cameron has access to and how approval works.">
        <div class="space-y-3">
            @foreach($this->toolCategories as $cat)
                @php
                    $colorClass = match($cat['color']) {
                        'orange' => 'text-orange-500',
                        'blue' => 'text-blue-500',
                        'green' => 'text-green-500',
                        'cyan' => 'text-cyan-500',
                        'purple' => 'text-purple-500',
                        'amber' => 'text-amber-500',
                        'pink' => 'text-pink-500',
                        'zinc' => 'text-zinc-500',
                        default => 'text-indigo-500',
                    };
                @endphp
                <flux:card class="p-4 {{ ! $cat['isConnected'] ? 'opacity-60' : '' }}">
                    <div class="flex items-center justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <flux:icon name="{{ $cat['icon'] }}" class="size-5 {{ $colorClass }} shrink-0" />
                            <div>
                                <flux:heading size="sm">{{ $cat['label'] }}</flux:heading>
                                @if(! $cat['isConnected'])
                                    <flux:text class="text-xs text-amber-600 dark:text-amber-400">
                                        Requires {{ str_replace('_', ' ', $cat['requiredField']) }} to be configured.
                                    </flux:text>
                                @endif
                            </div>
                        </div>
                        <flux:switch
                            wire:model.live="toolEnabled.{{ $cat['value'] }}"
                            wire:change="updateToolCategory('{{ $cat['value'] }}')"
                            :disabled="! $cat['isConnected']"
                        />
                    </div>

                    @if($cat['isConnected'] && ($this->toolEnabled[$cat['value']] ?? true))
                        <div class="mt-3 pt-3 border-t border-zinc-100 dark:border-zinc-800">
                            <flux:select
                                wire:model.live="toolApprovalModes.{{ $cat['value'] }}"
                                wire:change="updateToolCategory('{{ $cat['value'] }}')"
                                label="Approval mode"
                                size="sm"
                            >
                                <flux:select.option value="default">Default (tool decides)</flux:select.option>
                                <flux:select.option value="auto">Auto-approve all</flux:select.option>
                                <flux:select.option value="require_approval">Require approval for all</flux:select.option>
                            </flux:select>
                        </div>
                    @endif
                </flux:card>
            @endforeach
        </div>
    </x-pages::shop.settings-layout>
</section>
