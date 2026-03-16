<div class="flex items-start max-md:flex-col px-6 pb-6">
    <div class="me-10 w-full pb-4 md:w-[220px]">
        <flux:navlist aria-label="Shop Settings">
            <flux:navlist.item :href="route('shop.edit')" wire:navigate>General</flux:navlist.item>
            <flux:navlist.item :href="route('shop.tools')" wire:navigate>Tools</flux:navlist.item>
        </flux:navlist>
    </div>

    <flux:separator class="md:hidden" />

    <div class="flex-1 self-stretch max-md:pt-6">
        <flux:heading>{{ $heading ?? '' }}</flux:heading>
        <flux:subheading>{{ $subheading ?? '' }}</flux:subheading>

        <div class="mt-5 w-full max-w-2xl">
            {{ $slot }}
        </div>
    </div>
</div>
