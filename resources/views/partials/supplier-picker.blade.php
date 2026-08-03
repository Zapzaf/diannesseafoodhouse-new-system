{{--
    Searchable supplier <select>, enhanced client-side by public/js/supplier-picker.js
    (no AJAX — filters the already-rendered <option> list, fine for this dataset size).

    Required: $name, $suppliers (Collection of Supplier)
    Optional: $id, $selected, $required (bool), $placeholder, $emptyLabel,
              $selectClass (extra classes on the underlying <select>),
              $optionAttrs (callable(Supplier): string of extra raw HTML attributes per <option>)
--}}
@php
    $required = $required ?? false;
    $placeholder = $placeholder ?? 'Search supplier...';
    $emptyLabel = $emptyLabel ?? 'Select Supplier';
@endphp
<div class="supplier-combo position-relative">
    <input type="text" class="form-control supplier-search {{ $searchClass ?? '' }}" placeholder="{{ $placeholder }}" autocomplete="off">
    <select class="form-select supplier-select d-none {{ $selectClass ?? '' }}" name="{{ $name }}" @if(!empty($id)) id="{{ $id }}" @endif {{ $required ? 'required' : '' }}>
        <option value="">{{ $emptyLabel }}</option>
        @foreach($suppliers as $supplier)
        <option value="{{ $supplier->id }}" {!! isset($optionAttrs) ? $optionAttrs($supplier) : '' !!} {{ (string) ($selected ?? '') === (string) $supplier->id ? 'selected' : '' }}>{{ $supplier->name }}</option>
        @endforeach
    </select>
    <div class="dropdown-menu supplier-dropdown-menu w-100" style="max-height: 260px; overflow-y: auto;"></div>
</div>
