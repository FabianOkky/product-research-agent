<div class="mx-auto flex w-full max-w-3xl flex-col gap-6 p-6 lg:p-8">
    <div class="flex flex-col gap-1">
        <flux:heading size="xl">Riset Produk Baru</flux:heading>
        <flux:subheading>Ceritakan kebutuhanmu, dan agent akan mencarikan rekomendasi produk terbaik untukmu.</flux:subheading>
    </div>

    <form wire:submit="submit" class="flex flex-col gap-6">
        <flux:textarea
            wire:model="userInput"
            label="Kebutuhanmu"
            placeholder="Contoh: Saya butuh laptop untuk desain grafis dan editing video, budget sekitar 15 juta, baterai awet dan layar bagus."
            rows="6"
        />

        <div class="flex items-center justify-between gap-4">
            <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">
                Proses riset memakan waktu sekitar 30–60 detik.
            </flux:text>

            <flux:button
                type="submit"
                variant="primary"
                icon="sparkles"
                wire:target="submit"
                wire:loading.attr="disabled"
                class="shrink-0"
            >
                <span wire:loading.remove wire:target="submit">Mulai Riset</span>
                <span wire:loading wire:target="submit">Memulai…</span>
            </flux:button>
        </div>
    </form>
</div>
