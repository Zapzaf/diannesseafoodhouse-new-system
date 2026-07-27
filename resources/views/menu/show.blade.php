@extends('layouts.app')

@section('page_title', $menu->name . ' - Dianne\'s Seafood House')

@section('content')
    <x-page-header :title="$menu->name" :subtitle="$menu->category_label . ' · ₱' . number_format($menu->selling_price, 2)" icon="coffee">
        <div class="d-flex gap-2">
            <a href="{{ route('menus.edit', $menu) }}" class="btn btn-light text-primary">
                <i data-lucide="edit" class="me-1"></i> Edit
            </a>
            <a href="{{ route('menus.index') }}" class="btn btn-light text-primary">
                <i data-lucide="arrow-left" class="me-1"></i> Back
            </a>
        </div>
    </x-page-header>

    <div class="container-xl px-4">
        @include('layouts.alerts')

        <div class="row g-4">
            <div class="col-md-5">
                <div class="card shadow-sm">
                    <div class="card-header fw-semibold">Menu Details</div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-5">Name</dt>
                            <dd class="col-sm-7">{{ $menu->name }}</dd>

                            @if($menu->menu_description)
                            <dt class="col-sm-5">Description</dt>
                            <dd class="col-sm-7 text-muted">{{ $menu->menu_description }}</dd>
                            @endif

                            <dt class="col-sm-5">Category</dt>
                            <dd class="col-sm-7"><span class="badge bg-secondary">{{ $menu->category_label }}</span></dd>

                            <dt class="col-sm-5">Selling Price</dt>
                            <dd class="col-sm-7 fw-bold text-primary">₱{{ number_format($menu->selling_price, 2) }}</dd>

                            <dt class="col-sm-5">Branch</dt>
                            <dd class="col-sm-7">{{ $menu->branch->name ?? '—' }}</dd>

                            @php
                                $unitCost = $menu->computeUnitCost();
                                $grossMargin = $unitCost > 0 && $menu->selling_price > 0
                                    ? (($menu->selling_price - $unitCost) / $menu->selling_price) * 100
                                    : null;
                            @endphp

                            <dt class="col-sm-5">Est. Unit Cost</dt>
                            <dd class="col-sm-7">₱{{ number_format($unitCost, 2) }}</dd>

                            @if($grossMargin !== null)
                            <dt class="col-sm-5">Gross Margin</dt>
                            <dd class="col-sm-7">
                                <span class="badge {{ $grossMargin >= 50 ? 'bg-success' : ($grossMargin >= 20 ? 'bg-warning text-white' : 'bg-danger') }}">
                                    {{ number_format($grossMargin, 1) }}%
                                </span>
                            </dd>
                            @endif
                        </dl>
                    </div>
                </div>
            </div>

            <div class="col-md-7">
                <div class="card shadow-sm">
                    <div class="card-header fw-semibold">Recipe / Bill of Materials</div>
                    <div class="card-body">
                        @if($menu->no_ingredients)
                        <div class="text-center text-muted py-3">
                            <i data-lucide="ban" class="mb-2"></i>
                            <div>This menu item is marked as "No Ingredients" and does not consume inventory stock.</div>
                        </div>
                        @else
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead class="table-light">
                                    <tr>
                                        <th>Ingredient</th>
                                        <th>Unit</th>
                                        <th class="text-end">Qty Required</th>
                                        <th class="text-end">Unit Cost</th>
                                        <th class="text-end">Ingredient Cost</th>
                                        <th class="text-end">Stock</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($menu->items as $item)
                                    @php
                                        $ingredientCost = (float) ($item->unit_price ?? 0) * (float) $item->pivot->quantity_required;
                                    @endphp
                                    <tr>
                                        <td class="fw-semibold">{{ $item->name }}</td>
                                        <td class="text-muted">{{ $item->unit ?? '—' }}</td>
                                        <td class="text-end">{{ number_format($item->pivot->quantity_required, 2) }}</td>
                                        <td class="text-end">₱{{ number_format($item->unit_price ?? 0, 2) }}</td>
                                        <td class="text-end">₱{{ number_format($ingredientCost, 2) }}</td>
                                        <td class="text-end">
                                            @php $isLow = (float) $item->quantity <= (float) $item->low_stock_threshold; @endphp
                                            <span class="{{ $isLow ? 'text-danger fw-semibold' : 'text-success' }}">
                                                {{ $item->quantity }}
                                            </span>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-3">No ingredients defined.</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                                @if($menu->items->count())
                                <tfoot class="table-light fw-bold">
                                    <tr>
                                        <td colspan="4" class="text-end">Total Cost per Unit:</td>
                                        <td class="text-end">₱{{ number_format($unitCost, 2) }}</td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                                @endif
                            </table>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection