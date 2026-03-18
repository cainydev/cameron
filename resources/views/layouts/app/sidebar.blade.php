<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-dvh bg-white dark:bg-zinc-800">
        <flux:sidebar sticky collapsible class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 [:where(&)]:w-72">
            <flux:sidebar.header>
                <flux:sidebar.brand :name="config('app.name')" href="{{ route('cameron') }}" wire:navigate>
                    <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center overflow-hidden rounded-md">
                        <img src="{{ Vite::asset('resources/images/cameron.png') }}" alt="{{ config('app.name') }}" class="size-full object-cover">
                    </x-slot>
                </flux:sidebar.brand>
                <flux:sidebar.collapse />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <!-- Main Navigation -->
                <flux:sidebar.item icon="flag" :href="route('goals')" :current="request()->routeIs('goals') || request()->routeIs('goal')" wire:navigate>
                    Goals
                </flux:sidebar.item>

                <!-- Cameron Chats -->
                <flux:sidebar.group class="grid mt-2">
                    <livewire:conversation-list />
                </flux:sidebar.group>
            </flux:sidebar.nav>

            <flux:spacer />

            {{-- Shop selector --}}
            @php $currentShop = auth()->user()->shops()->first(); @endphp
            @if($currentShop)
            <flux:dropdown position="top" align="start" class="w-full">
                <flux:sidebar.item icon="building-storefront" suffix-icon="chevron-up-down" class="w-full">
                    {{ $currentShop->name }}
                </flux:sidebar.item>
                <flux:menu>
                    <flux:menu.radio.group>
                        @foreach(auth()->user()->shops as $shop)
                            <flux:menu.radio :checked="$shop->id === $currentShop->id">
                                {{ $shop->name }}
                            </flux:menu.radio>
                        @endforeach
                    </flux:menu.radio.group>
                    <flux:menu.separator />
                    <flux:menu.item icon="plus" :href="route('shop.setup')" wire:navigate>
                        New Shop
                    </flux:menu.item>
                    <flux:menu.item icon="cog-6-tooth" :href="route('shop.edit')" wire:navigate>
                        Configure Shop
                    </flux:menu.item>
                </flux:menu>
            </flux:dropdown>
            @endif

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @fluxScripts
    </body>
</html>
