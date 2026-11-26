@extends('layouts.app')
@section('title', 'Hand Filter Studio — WeDi')
@section('content')
<div class="page-head">
    <div>
        <p class="page-kicker">{{ __('Creative Space') }}</p>
        <h1>Hand Filter Studio</h1>
        <p>{{ __('Bentuk kotak dari kedua tanganmu, lalu mainkan efek di dalamnya. Semua diproses di perangkatmu.') }}</p>
    </div>
    <a href="{{ route('games.index') }}" class="btn btn-secondary"><i class="ri-arrow-left-line" aria-hidden="true"></i> {{ __('Semua game') }}</a>
</div>

@php
    $filters = [
        ['glitch', 'ri-pulse-line', 'Glitch'],
        ['crt', 'ri-tv-2-line', 'CRT'],
        ['thermal', 'ri-temp-hot-line', 'Thermal'],
        ['rainbow', 'ri-rainbow-line', 'Rainbow'],
        ['mosaic', 'ri-grid-line', 'Mosaic'],
        ['dream', 'ri-moon-cloudy-line', 'Dream'],
    ];
@endphp

<div class="card hf hs-root" id="hand-filter">
    <div class="hs-bar">
        <button type="button" class="btn btn-primary hs-toggle" data-hf-toggle><i class="ri-camera-line" aria-hidden="true"></i><span data-hf-toggle-label>{{ __('Aktifkan kamera') }}</span></button>
        <span class="hs-status" data-hf-status data-state="off" role="status"><i class="hs-dot" aria-hidden="true"></i><span data-hf-status-text>{{ __('Kamera mati') }}</span></span>
        <span class="hs-chip" data-hf-hands hidden></span>
        <span class="hs-chip hf-rec" data-hf-rec hidden><i class="hs-dot" aria-hidden="true"></i><span data-hf-rec-time>00:00</span></span>
        <span class="hs-spacer"></span>
        <button type="button" class="icon-btn hs-icon" data-hf-help aria-label="{{ __('Panduan') }}" title="{{ __('Panduan') }}"><i class="ri-question-line" aria-hidden="true"></i></button>
    </div>
    <p class="hs-privacy"><i class="ri-shield-check-line" aria-hidden="true"></i> {{ __('Kamera hanya menyala setelah kamu mengizinkannya. Video diproses di perangkatmu; foto dan rekaman hanya tersimpan jika kamu mengunduhnya.') }}</p>

    <div class="hf-board" data-hf-board>
        <canvas class="hf-canvas" data-hf-canvas aria-label="{{ __('Pratinjau kamera dengan filter') }}"></canvas>
        <video class="hf-video" data-hf-video playsinline muted></video>
        <div class="hf-label" data-hf-label aria-live="polite"></div>
        <div class="hf-empty" data-hf-empty>
            <i class="ri-magic-line" aria-hidden="true"></i>
            <h3>{{ __('Aktifkan kamera untuk mulai') }}</h3>
            <p>{{ __('Satukan ujung ibu jari dan telunjuk kedua tangan untuk memunculkan kotak, lalu renggangkan.') }}</p>
        </div>
        <div class="hs-hint" data-hf-hint hidden><i class="ri-hand-heart-line" aria-hidden="true"></i> {{ __('Tangan tidak terlihat. Dekatkan ke kamera dan pastikan cahaya cukup.') }}</div>
        <div class="hf-flash" data-hf-flash></div>
        <div class="hs-guide" data-hf-guide hidden role="dialog" aria-label="{{ __('Panduan Hand Filter') }}">
            <div class="hs-guide-card">
                <h3>{{ __('Cara memakai Hand Filter') }}</h3>
                <ol class="hs-steps">
                    <li style="--i:0"><span class="hs-emoji hs-emoji-0" aria-hidden="true">🙌</span><strong>{{ __('Tunjukkan dua tangan') }}</strong><small>{{ __('Arahkan kedua telapak ke kamera.') }}</small></li>
                    <li style="--i:1"><span class="hs-emoji hs-emoji-2" aria-hidden="true">🤏</span><strong>{{ __('Satukan ujung jari') }}</strong><small>{{ __('Temukan ibu jari dengan ibu jari, telunjuk dengan telunjuk: kotak aktif.') }}</small></li>
                    <li style="--i:2"><span class="hs-emoji hs-emoji-1" aria-hidden="true">↔️</span><strong>{{ __('Renggangkan') }}</strong><small>{{ __('Tarik tangan untuk membentuk kotak; efek muncul di dalamnya.') }}</small></li>
                    <li style="--i:3"><span class="hs-emoji hs-emoji-2" aria-hidden="true">✊</span><strong>{{ __('Lipat 3 jari, lalu buka') }}</strong><small>{{ __('Ganti filter. Bisa juga klik tombol filter.') }}</small></li>
                    <li style="--i:4"><span class="hs-emoji hs-emoji-0" aria-hidden="true">✌️</span><strong>{{ __('Satu tangan: peace') }}</strong><small>{{ __('Latar kamera menjadi blur sinematik.') }}</small></li>
                    <li style="--i:5"><span class="hs-emoji hs-emoji-1" aria-hidden="true">🖐️</span><strong>{{ __('Telapak terbuka') }}</strong><small>{{ __('Muncul portal ala Doctor Strange di telapak.') }}</small></li>
                </ol>
                <p class="hs-tip"><i class="ri-lightbulb-flash-line" aria-hidden="true"></i> {{ __('Tips: cahaya cukup, tangan 30–60 cm dari kamera, latar tidak ramai.') }}</p>
                <button type="button" class="btn btn-primary" data-hf-guide-ok>{{ __('Mengerti, ayo mulai!') }}</button>
            </div>
        </div>
    </div>

    <div class="hf-controls">
        <div class="hf-group">
            <span class="hs-set-title">{{ __('Filter di dalam kotak') }}</span>
            <div class="hf-filters" role="group" aria-label="{{ __('Pilih filter') }}">
                @foreach($filters as [$key, $icon, $label])
                <button type="button" class="hf-chip {{ $loop->first ? 'active' : '' }}" data-hf-filter="{{ $key }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}"><i class="{{ $icon }}" aria-hidden="true"></i>{{ $label }}</button>
                @endforeach
            </div>
        </div>
        <div class="hf-group">
            <span class="hs-set-title">{{ __('Warna garis kotak') }}</span>
            <div class="hf-colors" role="group">
                @foreach([['#00f0ff', 'Cyan'], ['#ff4d8d', 'Pink'], ['#ffd166', 'Gold'], ['#7dffb0', 'Mint'], ['#b48cff', 'Violet']] as [$c, $n])
                <button type="button" class="hf-swatch {{ $loop->first ? 'active' : '' }}" style="--c:{{ $c }}" data-hf-color="{{ $c }}" aria-label="{{ $n }}" title="{{ $n }}"></button>
                @endforeach
            </div>
        </div>
        <div class="hf-group hf-actions">
            <label class="hs-switch"><input type="checkbox" data-hf-skeleton checked><span></span>{{ __('Kerangka tangan') }}</label>
            <button type="button" class="btn btn-secondary btn-sm" data-hf-reset><i class="ri-refresh-line" aria-hidden="true"></i> {{ __('Reset kotak') }}</button>
            <button type="button" class="btn btn-primary btn-sm" data-hf-snap disabled><i class="ri-camera-lens-line" aria-hidden="true"></i> {{ __('Ambil foto') }}</button>
            <button type="button" class="btn btn-secondary btn-sm" data-hf-record disabled><i class="ri-record-circle-line" aria-hidden="true"></i> <span data-hf-record-label>{{ __('Rekam') }}</span></button>
        </div>
    </div>
</div>
@endsection
@push('scripts')<script type="module" src="{{ asset('js/hand-filter.js') }}?v={{ filemtime(public_path('js/hand-filter.js')) }}"></script>@endpush
