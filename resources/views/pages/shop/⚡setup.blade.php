<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Connect Google')] #[Layout('layouts.auth')] class extends Component
{
};
?>

<div class="flex flex-col items-center gap-6 text-center">
    <div>
        <flux:heading size="xl">Connect Google</flux:heading>
        <flux:text class="mt-1 text-zinc-500 max-w-xs">
            Cameron needs access to your Google account to manage ads, analytics, and search data.
        </flux:text>
    </div>

    <div class="w-full flex flex-col gap-3">
        <flux:button
            variant="primary"
            :href="route('google.redirect')"
            icon="arrow-top-right-on-square"
            class="w-full"
        >
            Connect Google Account
        </flux:button>

        <flux:text class="text-xs text-zinc-400 dark:text-zinc-500">
            You'll be redirected to Google to grant access. Cameron only requests read permissions for Analytics and Search Console, and manage permissions for Google Ads.
        </flux:text>
    </div>
</div>
