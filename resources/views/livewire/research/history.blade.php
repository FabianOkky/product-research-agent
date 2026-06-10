<div class="mx-auto flex w-full max-w-3xl flex-col gap-6 p-6 lg:p-8">
    <div class="flex items-center justify-between gap-4">
        <div class="flex flex-col gap-1">
            <flux:heading size="xl">Riwayat Riset</flux:heading>
            <flux:subheading>Semua riset produk yang pernah kamu jalankan.</flux:subheading>
        </div>

        <flux:button :href="route('research.create')" variant="primary" icon="sparkles" wire:navigate class="shrink-0">
            Riset Baru
        </flux:button>
    </div>

    @forelse ($jobs as $job)
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
                <flux:subheading>Mulai riset pertamamu untuk menemukan rekomendasi produk terbaik.</flux:subheading>
            </div>

            <flux:button :href="route('research.create')" variant="primary" icon="sparkles" wire:navigate>
                Mulai yang pertama
            </flux:button>
        </flux:card>
    @endforelse

    @if ($jobs->hasPages())
        <flux:pagination :paginator="$jobs" />
    @endif
</div>
