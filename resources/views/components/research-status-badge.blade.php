@props(['status'])

@php
    $statusLabel = match ($status) {
        'pending' => 'Menunggu antrian',
        'processing' => 'Sedang diproses',
        'done' => 'Selesai',
        'failed' => 'Gagal',
        default => $status,
    };

    $statusColor = match ($status) {
        'processing' => 'blue',
        'done' => 'green',
        'failed' => 'red',
        default => 'zinc',
    };

    $statusIcon = match ($status) {
        'processing' => 'arrow-path',
        'done' => 'check',
        'failed' => 'x-mark',
        default => 'clock',
    };
@endphp

<flux:badge :color="$statusColor" :icon="$statusIcon" {{ $attributes }}>{{ $statusLabel }}</flux:badge>
