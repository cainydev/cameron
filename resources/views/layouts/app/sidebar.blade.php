<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <flux:sidebar.brand :name="config('app.name')" href="{{ route('cameron') }}" wire:navigate>
                    <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center overflow-hidden rounded-md">
                        <img src="{{ Vite::asset('resources/images/cameron.png') }}" alt="{{ config('app.name') }}" class="size-full object-cover">
                    </x-slot>
                </flux:sidebar.brand>
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <!-- Main Navigation -->
                <flux:sidebar.item icon="home" :href="route('cameron')" :current="request()->routeIs('cameron')" wire:navigate>
                    Cameron
                </flux:sidebar.item>
                <flux:sidebar.item icon="flag" :href="route('goals')" :current="request()->routeIs('goals')" wire:navigate>
                    Goals
                </flux:sidebar.item>

                <!-- Task Agents -->
                @php
                    $tasks = \App\Models\AgentTask::query()
                        ->with('goal')
                        ->whereHas('goal')
                        ->orderByRaw("CASE WHEN status = 'waiting_approval' THEN 1 WHEN status = 'running' THEN 2 WHEN status = 'pending' THEN 3 ELSE 4 END")
                        ->orderByDesc('updated_at')
                        ->get();
                @endphp

                @if($tasks->isNotEmpty())
                    <flux:sidebar.group heading="Task Agents" class="grid mt-2">
                        @foreach($tasks as $task)
                            <flux:sidebar.item
                                :icon="match($task->status->value) {
                                    'running' => 'loading',
                                    'waiting_approval' => 'clock',
                                    'completed' => 'check-circle-solid',
                                    'failed' => 'x-circle-solid',
                                    default => 'circle'
                                }"
                                :badge="$task->status->value === 'waiting_approval' ? '!' : null"
                                badge:color="amber"
                                :href="route('agent', $task->id)"
                                :current="request()->routeIs('agent', $task->id)"
                                wire:navigate
                            >
                                <div class="flex items-center gap-2 w-full">
                                    <span class="truncate">{{ $task->goal->name }}</span>
                                    @if($task->status->value === 'running')
                                        <flux:icon name="loading" class="animate-spin flex-shrink-0" variant="micro" />
                                    @endif
                                </div>
                            </flux:sidebar.item>
                        @endforeach
                    </flux:sidebar.group>
                @endif
            </flux:sidebar.nav>

            <flux:spacer />

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
