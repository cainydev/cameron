<?php

use App\Models\Shop;
use App\Services\GoogleAccountDiscoveryService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Shop Settings')] class extends Component
{
    #[Locked]
    public ?int $shopId = null;

    public string $name = '';

    public string $url = '';

    public string $timezone = 'UTC';

    public string $currency = 'USD';

    public string $ga4PropertyId = '';

    public string $adsCustomerId = '';

    public string $searchConsoleUrl = '';

    public string $baseInstructions = '';

    public string $brandGuidelines = '';

    public string $merchantCenterId = '';

    public string $targetRoas = '';

    public function mount(): void
    {
        $shop = Auth::user()->shops()->first();

        if ($shop) {
            $this->shopId = $shop->id;
            $this->name = $shop->name;
            $this->url = $shop->url ?? '';
            $this->timezone = $shop->timezone ?? 'UTC';
            $this->currency = $shop->currency ?? 'USD';
            $this->ga4PropertyId = $shop->ga4_property_id ?? '';
            $this->adsCustomerId = $shop->google_ads_customer_id ?? '';
            $this->searchConsoleUrl = $shop->search_console_url ?? '';
            $this->merchantCenterId = $shop->merchant_center_id ?? '';
            $this->baseInstructions = $shop->base_instructions ?? '';
            $this->brandGuidelines = $shop->brand_guidelines ?? '';
            $this->targetRoas = $shop->target_roas ?? '';
        }
    }

    #[Computed]
    public function isFirstShop(): bool
    {
        return $this->shopId === null;
    }

    #[Computed]
    public function shop(): ?Shop
    {
        return $this->shopId ? Shop::find($this->shopId) : null;
    }

    #[Computed]
    public function ga4Properties(): array
    {
        $context = $this->shop ?? Auth::user();

        return (new GoogleAccountDiscoveryService($context))->getAccessibleGa4Properties();
    }

    #[Computed]
    public function adsCustomers(): array
    {
        $context = $this->shop ?? Auth::user();

        return (new GoogleAccountDiscoveryService($context))->getAccessibleAdsCustomers();
    }

    #[Computed]
    public function searchConsoleSites(): array
    {
        $context = $this->shop ?? Auth::user();

        return (new GoogleAccountDiscoveryService($context))->getAccessibleSearchConsoleSites();
    }

    #[Computed]
    public function merchantAccounts(): array
    {
        $context = $this->shop ?? Auth::user();

        return (new GoogleAccountDiscoveryService($context))->getAccessibleMerchantAccounts();
    }

    public function save(): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'timezone' => 'required|string|in:' . implode(',', timezone_identifiers_list()),
            'currency' => 'required|string|size:3',
        ]);

        $data = [
            'name' => $this->name,
            'url' => $this->url ?: null,
            'timezone' => $this->timezone,
            'currency' => $this->currency,
            'ga4_property_id' => $this->ga4PropertyId ?: null,
            'google_ads_customer_id' => $this->adsCustomerId ?: null,
            'search_console_url' => $this->searchConsoleUrl ?: null,
            'merchant_center_id' => $this->merchantCenterId ?: null,
            'base_instructions' => $this->baseInstructions ?: null,
            'brand_guidelines' => $this->brandGuidelines ?: null,
            'target_roas' => $this->targetRoas ?: null,
        ];

        $isCreating = $this->shopId === null;

        if ($this->shopId) {
            Shop::findOrFail($this->shopId)->update($data);
        } else {
            $shop = Shop::create(['user_id' => Auth::id(), ...$data]);
            $this->shopId = $shop->id;
        }

        if ($isCreating) {
            $this->redirect(route('cameron'), navigate: true);

            return;
        }

        $this->dispatch('shop-saved');
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

    @if(session('status') === 'google-connected')
        <div class="px-6 mb-2">
            <flux:callout icon="check-circle" color="green">
                <flux:callout.text>Google account connected successfully.</flux:callout.text>
            </flux:callout>
        </div>
    @endif

    @if($this->isFirstShop)
        <div class="px-6 pb-6">
            <flux:callout icon="information-circle" color="blue" class="mb-6">
                <flux:callout.heading>Welcome! Let's set up your first shop.</flux:callout.heading>
                <flux:callout.text>
                    Give your shop a name and connect your Google accounts. You can update these settings at any time.
                </flux:callout.text>
            </flux:callout>

            <form wire:submit="save" class="space-y-6 max-w-lg">
                @include('pages.shop._general-form')

                <flux:button type="submit" variant="primary">Create Shop</flux:button>
            </form>
        </div>
    @else
        <x-pages::shop.settings-layout heading="General" subheading="Update your shop details and Google integrations.">
            <form wire:submit="save" class="my-6 w-full space-y-6">
                @include('pages.shop._general-form')

                <div class="flex items-center gap-4">
                    <flux:button type="submit" variant="primary">Save Changes</flux:button>

                    <x-action-message on="shop-saved">
                        Saved.
                    </x-action-message>
                </div>
            </form>
        </x-pages::shop.settings-layout>
    @endif
</section>
