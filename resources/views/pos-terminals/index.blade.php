@extends('layouts.app')

@section('page_title', 'POS Terminals - Dianne\'s Seafood House')

@section('content')
    <x-page-header title="POS Terminals" subtitle="Manage cash registers / terminals used for cash shifts and Z Readings" icon="monitor">
    </x-page-header>

    <div class="container-xl px-4">
        @include('layouts.alerts')

        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card shadow-sm">
                    <div class="card-header fw-semibold"><i data-lucide="plus-circle" class="me-1"></i> Add Terminal</div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('pos-terminals.store') }}">
                            @csrf
                            @if(auth()->user()->isAdmin())
                            <div class="mb-3">
                                <label class="form-label fw-bold">Branch <span class="text-danger">*</span></label>
                                <select name="branch_id" class="form-select @error('branch_id') is-invalid @enderror" required>
                                    <option value="">Select Branch</option>
                                    @foreach($branches as $branch)
                                    <option value="{{ $branch->id }}" {{ (string) old('branch_id', $selectedBranchId) === (string) $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
                                    @endforeach
                                </select>
                                @error('branch_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            @else
                            <input type="hidden" name="branch_id" value="{{ auth()->user()->branch_id }}">
                            @endif
                            <div class="mb-3">
                                <label class="form-label fw-bold">Terminal Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" placeholder="e.g. Front Counter" required>
                                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Terminal Code <span class="text-danger">*</span></label>
                                <input type="text" name="code" class="form-control @error('code') is-invalid @enderror" value="{{ old('code') }}" placeholder="e.g. T1" required>
                                @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <div class="form-text">Must be unique per branch. Used in Z Reading numbers.</div>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">
                                <i data-lucide="save" class="me-1"></i> Add Terminal
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-header fw-semibold"><i data-lucide="monitor" class="me-1"></i> Terminals</div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Code</th>
                                        <th>Name</th>
                                        <th>Branch</th>
                                        <th>Status</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($terminals as $terminal)
                                    <tr>
                                        <td class="fw-semibold">{{ $terminal->code }}</td>
                                        <td>{{ $terminal->name }}</td>
                                        <td class="text-muted small">{{ $terminal->branch->name ?? '—' }}</td>
                                        <td>
                                            <span class="badge {{ $terminal->is_active ? 'bg-success' : 'bg-secondary' }}">
                                                {{ $terminal->is_active ? 'Active' : 'Inactive' }}
                                            </span>
                                        </td>
                                        <td class="text-end text-nowrap">
                                            <form action="{{ route('pos-terminals.toggle-active', $terminal) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-secondary" title="{{ $terminal->is_active ? 'Deactivate' : 'Activate' }}">
                                                    <i data-lucide="{{ $terminal->is_active ? 'toggle-right' : 'toggle-left' }}"></i>
                                                </button>
                                            </form>
                                            <form action="{{ route('pos-terminals.destroy', $terminal) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this terminal?')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                                    <i data-lucide="trash-2"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr><td colspan="5" class="text-center text-muted py-4">No POS terminals yet.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
