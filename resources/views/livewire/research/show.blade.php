<div class="mx-auto flex w-full max-w-3xl flex-col gap-6 p-6 lg:p-8">
    <div class="flex items-center justify-between gap-4">
        <flux:heading size="xl">Hasil Riset</flux:heading>
        <x-research-status-badge :status="$job->status" />
    </div>

    <div class="flex flex-col gap-2">
        <flux:subheading>Kebutuhanmu</flux:subheading>
        <flux:text>{{ $job->user_input }}</flux:text>
    </div>

    @if ($job->status === 'failed')
        <flux:callout variant="danger" icon="exclamation-triangle">
            <flux:callout.heading>Riset gagal diproses</flux:callout.heading>
            <flux:callout.text>{{ $job->error ?: 'Terjadi kesalahan yang tidak diketahui. Silakan coba lagi.' }}</flux:callout.text>

            <x-slot name="actions">
                <flux:button wire:click="retry" variant="primary" size="sm" icon="arrow-path">Coba lagi</flux:button>
            </x-slot>
        </flux:callout>
    @endif

    {{-- Progress tracker. Polling only runs while the job is pending/processing; the
         attribute disappears once it settles, which stops Livewire from polling. --}}
    <div
        @if ($this->polling) wire:poll.2s="refreshJob" @endif
        class="flex flex-col gap-4 rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900"
    >
        @unless ($job->status === 'failed')
            <flux:progress :value="$job->progress" :color="$job->status === 'done' ? 'green' : 'blue'" />
        @endunless

        <div class="flex flex-col gap-3">
            @foreach ($steps as $i => $label)
                @php($state = $this->stepState($i))
                <div wire:key="step-{{ $i }}" data-step-state="{{ $state }}" class="flex items-center gap-3">
                    @if ($state === 'done')
                        <flux:icon.check-circle variant="solid" class="size-5 shrink-0 text-green-500" />
                    @elseif ($state === 'active')
                        <flux:icon.loading class="size-5 shrink-0 text-blue-500" />
                    @elseif ($state === 'failed')
                        <flux:icon.x-circle variant="solid" class="size-5 shrink-0 text-red-500" />
                    @else
                        <flux:icon.clock class="size-5 shrink-0 text-zinc-300 dark:text-zinc-600" />
                    @endif

                    <flux:text class="{{ $state === 'waiting' ? 'text-zinc-400 dark:text-zinc-500' : 'text-zinc-800 dark:text-zinc-100' }}">
                        {{ $label }}
                    </flux:text>
                </div>
            @endforeach
        </div>

        @if ($this->polling)
            <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">
                Halaman ini memperbarui otomatis. Proses biasanya selesai dalam 30–60 detik.
            </flux:text>
        @endif
    </div>

    @if ($job->status === 'done' && $job->report)
        <div class="flex flex-col gap-2">
            <flux:subheading>Laporan</flux:subheading>

            <flux:card>
                {{-- Markdown dari LLM dirender aman: HTML mentah di-strip & tautan
                     berbahaya (javascript:) dibuang sebelum di-echo. Tabel dipoles
                     (border, header abu, baris zebra) & bisa di-scroll horizontal di
                     layar kecil; daftar dibuat lebih rapat agar enak dibaca. --}}
                <div class="overflow-x-auto">
                    <div @class([
                        'prose prose-sm max-w-none dark:prose-invert',
                        'prose-li:my-1 prose-ul:my-2 prose-headings:mb-2 prose-headings:mt-6 first:prose-headings:mt-0',
                        '[&_table]:w-full [&_table]:border-collapse [&_table]:text-sm',
                        '[&_th]:border [&_th]:border-zinc-200 [&_th]:bg-zinc-100 [&_th]:p-2 [&_th]:text-left',
                        '[&_td]:border [&_td]:border-zinc-200 [&_td]:p-2 [&_td]:align-top',
                        'dark:[&_th]:border-zinc-700 dark:[&_th]:bg-zinc-800 dark:[&_td]:border-zinc-700',
                        '[&_tbody_tr:nth-child(even)]:bg-zinc-50 dark:[&_tbody_tr:nth-child(even)]:bg-zinc-800/40',
                    ])>
                        {!! \Illuminate\Support\Str::markdown($job->report ?? '', [
                            'html_input' => 'strip',
                            'allow_unsafe_links' => false,
                        ]) !!}
                    </div>
                </div>
            </flux:card>
        </div>
    @endif
</div>
