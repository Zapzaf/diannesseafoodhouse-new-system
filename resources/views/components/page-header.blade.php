@props(['title' => '', 'subtitle' => null, 'icon' => 'grid'])

<header class="page-header page-header-dark bg-gradient-primary-to-secondary pb-10">
    <div class="container-xl px-4">
        <div class="page-header-content pt-4">
            <div class="row align-items-center justify-content-between">
                <div class="col-auto mt-4">
                    <h1 class="page-header-title">
                        <div class="page-header-icon"><i data-feather="{{ $icon }}"></i></div>
                        {{ $title }}
                    </h1>
                    @if($subtitle)
                    <div class="page-header-subtitle">{{ $subtitle }}</div>
                    @endif
                </div>
                <div class="col-auto mt-4 d-flex gap-2">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </div>
</header>
