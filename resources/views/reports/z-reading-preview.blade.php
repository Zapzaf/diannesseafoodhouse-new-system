@extends('layouts.app')
@section('page_title', 'Preview Z Reading - Dianne\'s Seafood House')
@section('content')
<x-page-header title="Preview Z Reading" :subtitle="$terminal->name . ' (' . $terminal->code . ') — ' . \Carbon\Carbon::parse($businessDate)->format('M d, Y')" icon="lock">
    <a class="btn btn-primary" href="{{ route('reports.z-reading.index') }}">
        <i data-lucide="arrow-left" class="me-1"></i> Back
    </a>
</x-page-header>

<div class="container-xl px-4">
    @include('layouts.alerts')

    @if($existing)
    <div class="alert alert-warning">
        <i data-lucide="alert-triangle" class="me-1"></i>
        A Z Reading (<strong>{{ $existing->reading_number }}</strong>) has already been generated and locked for this terminal on this date.
        <a href="{{ route('reports.z-reading.show', $existing) }}">View it</a>, or an administrator can void it to allow regeneration.
    </div>
    @elseif($hasOpenShift)
    <div class="alert alert-danger">
        <i data-lucide="alert-triangle" class="me-1"></i>
        This terminal still has an open cash shift. All shifts must be closed before generating a Z Reading for the day.
    </div>
    @elseif(!$hasTransactions)
    <div class="alert alert-danger">
        <i data-lucide="alert-triangle" class="me-1"></i>
        There are no recorded transactions for this terminal on {{ \Carbon\Carbon::parse($businessDate)->format('M d, Y') }}.
        A Z Reading cannot be generated for a day with no sales.
    </div>
    @else
    <div class="alert alert-primary text-white">
        <i data-lucide="info" class="me-1"></i>
        Review the totals below. Generating the Z Reading will <strong>lock</strong> these numbers permanently for this terminal and date.
        It cannot be modified or regenerated afterward unless an administrator voids it.
    </div>
    @endif

    @include('reports.partials.reading-summary', ['summary' => $summary])

    @if(!$existing && !$hasOpenShift && $hasTransactions)
    <form method="POST" action="{{ route('reports.z-reading.store') }}" onsubmit="return confirm('Generate and lock this Z Reading? This cannot be undone.')">
        @csrf
        <input type="hidden" name="pos_terminal_id" value="{{ $terminal->id }}">
        <input type="hidden" name="business_date" value="{{ $businessDate }}">
        <button type="submit" class="btn btn-danger">
            <i data-lucide="lock" class="me-1"></i> Confirm &amp; Generate Z Reading
        </button>
    </form>
    @endif
</div>
@endsection
