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
    public function ga4Properties(): array
    {
        return (new GoogleAccountDiscoveryService(Auth::user()))->getAccessibleGa4Properties();
    }

    #[Computed]
    public function adsCustomers(): array
    {
        return (new GoogleAccountDiscoveryService(Auth::user()))->getAccessibleAdsCustomers();
    }

    #[Computed]
    public function searchConsoleSites(): array
    {
        return (new GoogleAccountDiscoveryService(Auth::user()))->getAccessibleSearchConsoleSites();
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

<div class="p-6 max-w-4xl">
    <div class="mb-6">
        <flux:heading size="xl">Shop Settings</flux:heading>
        <flux:text class="mt-1 text-zinc-500">Configure your shop details and Google integrations.</flux:text>
    </div>

    @if($this->isFirstShop)
        <flux:callout icon="information-circle" color="blue" class="mb-6">
            <flux:callout.heading>Welcome! Let's set up your first shop.</flux:callout.heading>
            <flux:callout.text>
                Give your shop a name and connect your Google accounts. You can update these settings at any time.
            </flux:callout.text>
        </flux:callout>
    @endif

    <form wire:submit="save" class="space-y-6">
        {{-- Identity + Locale --}}
        <flux:card class="p-5">
            <flux:heading class="mb-4">Identity</flux:heading>
            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="name" label="Shop Name" placeholder="My Online Store" required />
                <flux:input wire:model="url" label="Website URL" placeholder="https://mystore.com" type="url" />
                <flux:select wire:model="timezone" label="Timezone">
                    @foreach(timezone_identifiers_list() as $tz)
                        <flux:select.option value="{{ $tz }}">{{ $tz }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="currency" label="Currency">
                    <flux:select.option value="USD">USD — US Dollar</flux:select.option>
                    <flux:select.option value="EUR">EUR — Euro</flux:select.option>
                    <flux:select.option value="GBP">GBP — British Pound</flux:select.option>
                    <flux:select.option value="AUD">AUD — Australian Dollar</flux:select.option>
                    <flux:select.option value="CAD">CAD — Canadian Dollar</flux:select.option>
                    <flux:select.option value="JPY">JPY — Japanese Yen</flux:select.option>
                    <flux:select.option value="CHF">CHF — Swiss Franc</flux:select.option>
                    <flux:select.option value="NZD">NZD — New Zealand Dollar</flux:select.option>
                    <flux:select.option value="SGD">SGD — Singapore Dollar</flux:select.option>
                    <flux:select.option value="HKD">HKD — Hong Kong Dollar</flux:select.option>
                </flux:select>
            </div>
        </flux:card>

        {{-- Google Integrations --}}
        <flux:card class="p-5">
            <flux:heading class="mb-4">Google Integrations</flux:heading>
            <div class="grid grid-cols-3 gap-4">
                <flux:select wire:model="ga4PropertyId" label="GA4 Property">
                    <flux:select.option value="">— Not linked —</flux:select.option>
                    @foreach($this->ga4Properties as $property)
                        <flux:select.option value="{{ $property['id'] }}">
                            {{ $property['name'] }} ({{ $property['account_name'] }})
                        </flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="adsCustomerId" label="Google Ads">
                    <flux:select.option value="">— Not linked —</flux:select.option>
                    @foreach($this->adsCustomers as $customer)
                        <flux:select.option value="{{ $customer['id'] }}">{{ $customer['name'] }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="searchConsoleUrl" label="Search Console">
                    <flux:select.option value="">— Not linked —</flux:select.option>
                    @foreach($this->searchConsoleSites as $site)
                        <flux:select.option value="{{ $site['url'] }}">{{ $site['url'] }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        </flux:card>

        {{-- AI Settings --}}
        <flux:card class="p-5">
            <flux:heading class="mb-4">AI Settings</flux:heading>
            <div class="grid grid-cols-2 gap-4">
                <flux:input
                    wire:model="targetRoas"
                    label="Target ROAS"
                    placeholder="e.g. 4.0"
                    badge="optional"
                />
                <div></div>
                <flux:textarea
                    wire:model="baseInstructions"
                    label="Base Instructions"
                    placeholder="General instructions for Cameron when managing this shop..."
                    rows="3"
                    badge="optional"
                />
                <flux:textarea
                    wire:model="brandGuidelines"
                    label="Brand Guidelines"
                    placeholder="Tone of voice, target audience, messaging..."
                    rows="3"
                    badge="optional"
                />
            </div>
        </flux:card>

        <div class="flex items-center gap-4">
            <flux:button type="submit" variant="primary">
                {{ $this->isFirstShop ? 'Create Shop' : 'Save Changes' }}
            </flux:button>

            @unless($this->isFirstShop)
                <x-action-message on="shop-saved">
                    Saved.
                </x-action-message>
            @endunless
        </div>
    </form>
</div>
