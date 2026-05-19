@php
    $selectedPeriod = $filters['period'] ?? 'custom';
    $periodSeedDate = \Carbon\Carbon::parse($filters['date_from'] ?? $filters['date'] ?? now()->toDateString());
    $periodStartDate = $filters['date_from'] ?? $filters['date'] ?? now()->toDateString();
    $periodEndDate = $filters['date_to'] ?? $filters['date'] ?? now()->toDateString();
    $isCustomPeriod = $selectedPeriod === 'custom';
@endphp

<div class="col-md-2">
    <label class="form-label fw-bold">Period Type</label>
    <select name="period" class="form-select" data-role="period-type">
        <option value="custom" {{ $selectedPeriod === 'custom' ? 'selected' : '' }}>Custom Range</option>
        <option value="daily" {{ $selectedPeriod === 'daily' ? 'selected' : '' }}>Daily</option>
        <option value="weekly" {{ $selectedPeriod === 'weekly' ? 'selected' : '' }}>Weekly</option>
        <option value="monthly" {{ $selectedPeriod === 'monthly' ? 'selected' : '' }}>Monthly</option>
        <option value="yearly" {{ $selectedPeriod === 'yearly' ? 'selected' : '' }}>Yearly</option>
    </select>
</div>
<div class="col-md-2" data-role="weekly-group" style="display: {{ $selectedPeriod === 'weekly' ? '' : 'none' }};">
    <label class="form-label fw-bold">Week</label>
    <input type="week" data-role="weekly-date" class="form-control" value="{{ $periodSeedDate->format('o-\\WW') }}">
</div>
<div class="col-md-2" data-role="daily-group" style="display: {{ $selectedPeriod === 'daily' ? '' : 'none' }};">
    <label class="form-label fw-bold">Day</label>
    <input type="date" data-role="daily-date" class="form-control" value="{{ $periodSeedDate->format('Y-m-d') }}">
</div>
<div class="col-md-2" data-role="monthly-group" style="display: {{ $selectedPeriod === 'monthly' ? '' : 'none' }};">
    <label class="form-label fw-bold">Month</label>
    <input type="month" data-role="monthly-date" class="form-control" value="{{ $periodSeedDate->format('Y-m') }}">
</div>
<div class="col-md-2" data-role="yearly-group" style="display: {{ $selectedPeriod === 'yearly' ? '' : 'none' }};">
    <label class="form-label fw-bold">Year</label>
    <input type="number" data-role="yearly-date" class="form-control" min="2000" max="2100" step="1" placeholder="2026" value="{{ $periodSeedDate->format('Y') }}">
</div>
<div class="col-md-2" data-role="start-date-group" style="display: {{ $isCustomPeriod ? '' : 'none' }};">
    <label class="form-label fw-bold">Start Date</label>
    <input type="date" data-role="start-date" class="form-control" value="{{ $periodStartDate }}">
</div>
<div class="col-md-2" data-role="end-date-group" style="display: {{ $isCustomPeriod ? '' : 'none' }};">
    <label class="form-label fw-bold">End Date</label>
    <input type="date" data-role="end-date" class="form-control" value="{{ $periodEndDate }}">
</div>
<input type="hidden" name="date_from" data-role="resolved-date-from" value="{{ $periodStartDate }}">
<input type="hidden" name="date_to" data-role="resolved-date-to" value="{{ $periodEndDate }}">
@if(!empty($targetDateFieldName))
<input type="hidden" name="{{ $targetDateFieldName }}" data-role="resolved-target-date" value="{{ $filters['date'] ?? $periodEndDate }}">
@endif