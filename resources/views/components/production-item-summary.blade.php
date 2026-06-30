@props([
    'items' => collect(),
    'quantityField' => 'quantity_used',
    'empty' => 'No items',
    'variant' => 'input',
])

@php
    $items = collect($items);
    $visibleItems = $items->take(2);
    $remainingCount = max($items->count() - $visibleItems->count(), 0);
@endphp

<div class="production-item-summary">
    @forelse($visibleItems as $entry)
        @php
            $name = $entry->item?->name;

            if (! $name || $name === 'Delivery Material') {
                $name = $entry->deliveryItem?->description ?: $name;
            }

            $name = $name ?: 'Unavailable item';
            $quantity = (float) data_get($entry, $quantityField, 0);
            $unit = $entry->unit ?: $entry->item?->unit ?: $entry->deliveryItem?->unit;
        @endphp
        <div class="production-item-summary-row production-item-summary-row-{{ $variant }}">
            <span class="production-item-summary-name" title="{{ $name }}">{{ $name }}</span>
            <span class="production-item-summary-meta">
                {{ number_format($quantity, 2) }}@if($unit) {{ $unit }}@endif
            </span>
        </div>
    @empty
        <span class="text-muted small">{{ $empty }}</span>
    @endforelse

    @if($remainingCount > 0)
        <span class="production-item-summary-more">{{ $remainingCount }} more...</span>
    @endif
</div>
