<div class="card mb-4">
    <div class="card-header"><i class="me-1" data-lucide="table"></i> Table Details</div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label fw-bold">Branch <span class="text-danger">*</span></label>
                <select name="branch_id" id="branchId" class="form-select @error('branch_id') is-invalid @enderror" required {{ auth()->user()->isAdmin() ? '' : 'disabled' }}>
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}" {{ (string) old('branch_id', $table->branch_id ?? $selectedBranchId ?? '') === (string) $branch->id ? 'selected' : '' }}>
                            {{ $branch->name }}
                        </option>
                    @endforeach
                </select>
                @if(!auth()->user()->isAdmin())
                    <input type="hidden" name="branch_id" value="{{ old('branch_id', $table->branch_id ?? $selectedBranchId ?? '') }}">
                @endif
                @error('branch_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-6 mb-3">
                <label class="form-label fw-bold">Table Number <span class="text-danger">*</span></label>
                <input type="text" name="table_number" class="form-control @error('table_number') is-invalid @enderror" value="{{ old('table_number', $table->table_number ?? '') }}" maxlength="50" required>
                @error('table_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label fw-bold">Capacity <span class="text-danger">*</span></label>
                <input type="number" name="capacity" class="form-control @error('capacity') is-invalid @enderror" value="{{ old('capacity', $table->capacity ?? 1) }}" min="1" required>
                @error('capacity')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-6 mb-3">
                <label class="form-label fw-bold">Status</label>
                <select name="status" class="form-select @error('status') is-invalid @enderror">
                    @foreach(['available' => 'Available', 'occupied' => 'Occupied', 'reserved' => 'Reserved', 'cleaning' => 'Cleaning'] as $value => $label)
                        <option value="{{ $value }}" {{ old('status', $table->status ?? 'available') === $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="alert alert-info">Use "Assign" from the table list to attach a table to an order. Release tables when guests leave.</div>
    </div>
</div>
