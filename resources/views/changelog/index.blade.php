@extends('layouts.app')
@section('page_title', "What's New - Dianne's Seafood House")
@section('content')
    <x-page-header title="What's New" subtitle="Latest updates, improvements, and fixes to the system" icon="megaphone">
        @if(auth()->user()->isAdmin())
        <a href="{{ route('changelog.create') }}" class="btn btn-light text-primary">
            <i data-lucide="plus-circle" class="me-1"></i> New Update
        </a>
        @endif
    </x-page-header>

    <div class="container-xl px-4">
        @include('layouts.alerts')

        <div class="card shadow-sm mb-4">
            <div class="card-body py-3">
                <form method="GET" class="d-flex align-items-center flex-wrap gap-2">
                    <span class="text-muted small fw-semibold me-1">Filter:</span>
                    <a href="{{ route('changelog.index') }}" class="btn btn-sm {{ !$type ? 'btn-primary text-white' : 'btn-secondary text-white' }}">All</a>
                    @foreach(\App\Models\ChangelogUpdate::TYPES as $value => $meta)
                    <a href="{{ route('changelog.index', ['type' => $value]) }}" class="btn btn-sm {{ $type === $value ? 'btn-primary text-white' : 'btn-secondary text-white' }}">
                        <i data-lucide="{{ $meta['icon'] }}" style="width:13px;height:13px;" class="me-1"></i>{{ $meta['label'] }}
                    </a>
                    @endforeach
                </form>
            </div>
        </div>

        @if($updates->isEmpty())
        <div class="card shadow-sm">
            <div class="card-body text-center text-muted py-5">
                <i data-lucide="megaphone" style="width:36px;height:36px;opacity:.4;"></i>
                <div class="mt-2">No updates {{ $type ? 'of this type ' : '' }}yet. Check back soon!</div>
            </div>
        </div>
        @else
        <div class="row g-4 changelog-grid">
            @foreach($updates as $update)
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 shadow-sm changelog-card">
                    <div class="changelog-card-image">
                        @if($update->image_url)
                        <img src="{{ $update->image_url }}" alt="{{ $update->title }}" loading="lazy">
                        @else
                        <div class="changelog-card-image-placeholder">
                            <i data-lucide="{{ $update->type_icon }}"></i>
                        </div>
                        @endif
                        @unless($update->is_published)
                        <span class="badge bg-secondary changelog-card-draft-badge">Draft</span>
                        @endunless
                    </div>
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                            <span class="badge {{ $update->type_badge_class }} d-inline-flex align-items-center gap-1">
                                <i data-lucide="{{ $update->type_icon }}" style="width:12px;height:12px;"></i>
                                {{ $update->type_label }}
                            </span>
                            <span class="small text-muted text-nowrap">{{ $update->released_at->format('M d, Y') }}</span>
                        </div>
                        <h5 class="fw-bold mb-2">{{ $update->title }}</h5>
                        <p class="text-muted small mb-3 changelog-card-description">{{ $update->description }}</p>
                        @if(auth()->user()->isAdmin())
                        <div class="mt-auto d-flex gap-2 pt-2 border-top">
                            <a href="{{ route('changelog.edit', $update) }}" class="btn btn-sm btn-primary text-white flex-fill">
                                <i data-lucide="edit-2" style="width:13px;height:13px;" class="me-1"></i>Edit
                            </a>
                            <form action="{{ route('changelog.destroy', $update) }}" method="POST" class="flex-fill" onsubmit="return confirm('Delete this update?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-danger text-white w-100">
                                    <i data-lucide="trash-2" style="width:13px;height:13px;" class="me-1"></i>Delete
                                </button>
                            </form>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        @if($updates->hasPages())
        <div class="d-flex justify-content-center mt-4">
            {{ $updates->onEachSide(1)->links('pagination::bootstrap-5') }}
        </div>
        @endif
        @endif
    </div>
@endsection
