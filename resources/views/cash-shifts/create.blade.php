@extends('layouts.app')

@section('page_title', 'Open Cash Shift - Dianne\'s Seafood House')

@section('content')
    <x-page-header title="Open Cash Shift" subtitle="Start a new shift with an opening cash float" icon="wallet">
        <a class="btn btn-primary" href="{{ route('cash-shifts.index') }}">
            <i data-lucide="arrow-left" class="me-1"></i> Back
        </a>
    </x-page-header>

    <div class="container-xl px-4">
        @include('layouts.alerts')

        <div class="row justify-content-center">
            <div class="col-lg-6">
                <div class="card shadow-sm">
                    <div class="card-body">
                        @if($terminals->isEmpty())
                        <div class="alert alert-warning mb-0">
                            No active POS terminals are available for your branch. Ask an administrator to
                            <a href="{{ route('pos-terminals.index') }}">add one</a> first.
                        </div>
                        @else
                        <form method="POST" action="{{ route('cash-shifts.store') }}">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label fw-bold">POS Terminal <span class="text-danger">*</span></label>
                                <select name="pos_terminal_id" class="form-select @error('pos_terminal_id') is-invalid @enderror" required>
                                    <option value="">Select Terminal</option>
                                    @foreach($terminals as $terminal)
                                    <option value="{{ $terminal->id }}" {{ (string) old('pos_terminal_id') === (string) $terminal->id ? 'selected' : '' }}>{{ $terminal->name }} ({{ $terminal->code }})</option>
                                    @endforeach
                                </select>
                                @error('pos_terminal_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Opening Cash Float (PHP) <span class="text-danger">*</span></label>
                                <input type="number" name="opening_float" class="form-control @error('opening_float') is-invalid @enderror" value="{{ old('opening_float', 0) }}" step="0.01" min="0" required>
                                @error('opening_float')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <div class="form-text">Count and enter the starting cash in the drawer.</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Notes</label>
                                <textarea name="notes" rows="2" class="form-control">{{ old('notes') }}</textarea>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">
                                <i data-lucide="play" class="me-1"></i> Open Shift
                            </button>
                        </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
