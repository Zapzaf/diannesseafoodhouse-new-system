@extends('layouts.app')
@section('page_title', 'Supplier - Dianne Seafood House')
@section('content')
    <x-page-header :title="$supplier->name" :subtitle="$supplier->type === 'sole_proprietorship' ? 'Single Proprietorship' : 'Company'" icon="truck">
        <a href="{{ route('suppliers.index') }}" class="btn btn-light text-primary">
            <i data-lucide="arrow-left" class="me-1"></i> Back to Suppliers
        </a>
        @can('update', $supplier)
        <a href="{{ route('suppliers.edit', $supplier) }}" class="btn btn-primary">
            <i data-lucide="pencil" class="me-1"></i> Edit
        </a>
        @endcan
    </x-page-header>

    <div class="container-xl px-4">
        @include('layouts.alerts')

        <div class="row g-4 mb-4">
            <div class="col-md-6 col-xl-3">
                <div class="card shadow-sm h-100 detail-stat-card">
                    <div class="card-body">
                        <span class="detail-stat-label">Deliveries</span>
                        <div class="detail-stat-value">{{ $supplier->deliveries_count }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="card shadow-sm h-100 detail-stat-card">
                    <div class="card-body">
                        <span class="detail-stat-label">Purchase Vouchers</span>
                        <div class="detail-stat-value">{{ $supplier->purchase_vouchers_count }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="card shadow-sm h-100 detail-stat-card">
                    <div class="card-body">
                        <span class="detail-stat-label">Check Vouchers</span>
                        <div class="detail-stat-value">{{ $supplier->check_vouchers_count }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="card shadow-sm h-100 detail-stat-card">
                    <div class="card-body">
                        <span class="detail-stat-label">Petty Cash Vouchers</span>
                        <div class="detail-stat-value">{{ $supplier->petty_cash_vouchers_count }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header fw-semibold">Identity</div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-5 text-muted">Supplier Type</dt>
                            <dd class="col-sm-7">{{ $supplier->type === 'sole_proprietorship' ? 'Single Proprietorship' : 'Company' }}</dd>

                            @if($supplier->type === 'sole_proprietorship')
                            <dt class="col-sm-5 text-muted">Business Name</dt>
                            <dd class="col-sm-7">{{ $supplier->business_name ?? '—' }}</dd>

                            <dt class="col-sm-5 text-muted">Owner's Name</dt>
                            <dd class="col-sm-7">{{ $supplier->owner_name ?? '—' }}</dd>
                            @else
                            <dt class="col-sm-5 text-muted">Company Name</dt>
                            <dd class="col-sm-7">{{ $supplier->company_name ?? '—' }}</dd>
                            @endif

                            <dt class="col-sm-5 text-muted">TIN</dt>
                            <dd class="col-sm-7">{{ $supplier->tin ?? '—' }}</dd>

                            <dt class="col-sm-5 text-muted">VAT Status</dt>
                            <dd class="col-sm-7">{{ $supplier->is_vat_registered ? 'VAT-registered' : 'Non-VAT' }}</dd>
                        </dl>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header fw-semibold">Contact</div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-5 text-muted">Contact Person</dt>
                            <dd class="col-sm-7">{{ $supplier->contact_person ?? '—' }}</dd>

                            <dt class="col-sm-5 text-muted">Phone</dt>
                            <dd class="col-sm-7">{{ $supplier->phone ?? '—' }}</dd>

                            <dt class="col-sm-5 text-muted">Email</dt>
                            <dd class="col-sm-7">{{ $supplier->email ?? '—' }}</dd>

                            <dt class="col-sm-5 text-muted">Address</dt>
                            <dd class="col-sm-7">{{ $supplier->address ?? '—' }}</dd>
                        </dl>
                    </div>
                </div>
            </div>

            @if($supplier->notes)
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header fw-semibold">Notes</div>
                    <div class="card-body">
                        <p class="mb-0">{{ $supplier->notes }}</p>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
@endsection
