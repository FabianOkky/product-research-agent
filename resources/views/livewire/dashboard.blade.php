<div class="mx-auto flex w-full max-w-5xl flex-col gap-6 p-6 lg:p-8">
    {{-- Header --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex flex-col gap-1">
            <flux:heading size="xl">Selamat datang, {{ auth()->user()->name }} 👋</flux:heading>
            <flux:subheading>Ringkasan riset produkmu dalam satu pandang.</flux:subheading>
        </div>

        <flux:button :href="route('research.create')" variant="primary" icon="sparkles" wire:navigate class="shrink-0">
            Riset Baru
        </flux:button>
    </div>

    {{-- Stat cards --}}
    @php
        $cards = [
            ['label' => 'Total Riset', 'value' => $stats['total'], 'icon' => 'document-magnifying-glass', 'color' => 'text-zinc-500 dark:text-zinc-400'],
            ['label' => 'Selesai', 'value' => $stats['done'], 'icon' => 'check-circle', 'color' => 'text-green-600 dark:text-green-400'],
            ['label' => 'Berjalan', 'value' => $stats['in_progress'], 'icon' => 'arrow-path', 'color' => 'text-blue-600 dark:text-blue-400'],
            ['label' => 'Gagal', 'value' => $stats['failed'], 'icon' => 'x-circle', 'color' => 'text-red-600 dark:text-red-400'],
        ];
    @endphp

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($cards as $card)
            <flux:card class="flex items-center gap-4">
                <flux:icon :name="$card['icon']" class="size-8 shrink-0 {{ $card['color'] }}" />
                <div class="flex flex-col">
                    <flux:heading size="xl">{{ $card['value'] }}</flux:heading>
                    <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">{{ $card['label'] }}</flux:text>
                </div>
            </flux:card>
        @endforeach
    </div>

    {{-- Recent research --}}
    <div class="flex flex-col gap-3">
        <div class="flex items-center justify-between gap-4">
            <flux:heading size="lg">Riset Terbaru</flux:heading>

            @if ($stats['total'] > count($recentJobs))
                <flux:link :href="route('research.history')" wire:navigate>Lihat semua</flux:link>
            @endif
        </div>

        @forelse ($recentJobs as $job)
            <a
                href="{{ route('research.show', $job) }}"
                wire:navigate
                wire:key="job-{{ $job->uuid }}"
                class="block rounded-xl transition hover:opacity-80 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
            >
                <flux:card class="flex items-start justify-between gap-4">
                    <div class="flex min-w-0 flex-col gap-1">
                        <flux:text class="truncate font-medium text-zinc-800 dark:text-zinc-100">
                            {{ \Illuminate\Support\Str::limit($job->user_input, 120) }}
                        </flux:text>
                        <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">
                            {{ $job->created_at->diffForHumans() }}
                        </flux:text>
                    </div>

                    <x-research-status-badge :status="$job->status" class="shrink-0" />
                </flux:card>
            </a>
        @empty
            <flux:card class="flex flex-col items-center gap-4 py-12 text-center">
                <flux:icon.sparkles class="size-12 text-zinc-300 dark:text-zinc-600" />

                <div class="flex flex-col gap-1">
                    <flux:heading size="lg">Belum ada riset</flux:heading>
                    <flux:subheading>
                        Jelaskan kebutuhanmu dengan bahasa biasa, lalu biarkan AI mencarikan & merangkum
                        rekomendasi produk terbaik untukmu.
                    </flux:subheading>
                </div>

                <flux:button :href="route('research.create')" variant="primary" icon="sparkles" wire:navigate>
                    Mulai riset pertama
                </flux:button>
            </flux:card>
        @endforelse
    </div>
</div>
