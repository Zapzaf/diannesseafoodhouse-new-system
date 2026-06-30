@props(['title' => '', 'subtitle' => null, 'icon' => 'grid'])

<div class="page-header">
    <div class="container-xl px-4">
        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
            <div class="page-header-heading d-flex align-items-start gap-2 min-w-0">
                @if($icon)
                <span class="page-header-icon flex-shrink-0">
                    <i data-lucide="{{ $icon }}"></i>
                </span>
                @endif
                <div class="min-w-0">
                    <h4 class="page-header-title fw-bold mb-0 text-main">{{ $title }}</h4>
                    @if($subtitle)
                    <p class="page-header-subtitle text-muted mb-0 small">{{ $subtitle }}</p>
                    @endif
                </div>
            </div>
            @if(trim((string) $slot) !== '')
            <div class="page-header-actions d-flex align-items-center gap-2 flex-wrap">
                {{ $slot }}
            </div>
            @endif
        </div>
    </div>
</div>
