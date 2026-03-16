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
    <div class="mb-4 flex items-center justify-between">
        <flux:heading>Google Integrations</flux:heading>
        @if($this->shopId)
            <flux:button
                size="sm"
                icon="arrow-path"
                href="{{ route('google.redirect', ['shop' => $this->shopId]) }}"
            >
                Reconnect Google
            </flux:button>
        @endif
    </div>

    @if($this->shopId && !($this->shop?->hasGoogleConnected()))
        <flux:callout icon="exclamation-triangle" color="yellow" class="mb-4">
            <flux:callout.text>No Google account connected for this shop. Click "Reconnect Google" to authorize.</flux:callout.text>
        </flux:callout>
    @endif

    <div class="grid grid-cols-2 gap-4">
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

        <flux:select wire:model="merchantCenterId" label="Merchant Center" badge="optional">
            <flux:select.option value="">— Not linked —</flux:select.option>
            @foreach($this->merchantAccounts as $account)
                <flux:select.option value="{{ $account['id'] }}">{{ $account['name'] }}</flux:select.option>
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
