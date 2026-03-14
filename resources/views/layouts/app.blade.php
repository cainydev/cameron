<x-layouts::app.sidebar :title="$title ?? null">
    <flux:main class="min-h-0 flex flex-col">
        {{ $slot }}
    </flux:main>
</x-layouts::app.sidebar>
