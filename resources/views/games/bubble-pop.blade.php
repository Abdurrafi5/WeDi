@extends('layouts.app')
@section('title', __('Bubble Pop — WeDi'))
@section('content')
<div class="page-head">
    <div>
        <p class="page-kicker">{{ __('Mini Game') }}</p>
        <h1>Bubble Pop</h1>
        <p>{{ __('Pecahkan gelembung dengan ujung telunjukmu lewat webcam — atau pakai mouse/sentuhan.') }}</p>
    </div>
    <a href="{{ route('games.index') }}" class="btn btn-secondary"><i class="ri-arrow-left-line" aria-hidden="true"></i> {{ __('Semua game') }}</a>
</div>

<div class="card bp hs-root" id="bubble-pop" data-best="{{ (int) $best }}" data-score-url="{{ route('games.saveScore') }}">
    @include('partials.hand-bar', ['ar' => true])

    <div class="bp-hud" aria-live="off">
        <div class="bp-pill"><i class="ri-timer-line" aria-hidden="true"></i><span><small>{{ __('Waktu') }}</small><b data-bp-time>60</b></span></div>
        <div class="bp-pill bp-score"><i class="ri-star-smile-line" aria-hidden="true"></i><span><small>{{ __('Skor') }}</small><b data-bp-score>0</b></span></div>
        <div class="bp-pill"><i class="ri-stack-line" aria-hidden="true"></i><span><small>{{ __('Level') }}</small><b data-bp-level>1</b></span></div>
        <div class="bp-pill" data-bp-combo-pill><i class="ri-fire-line" aria-hidden="true"></i><span><small>{{ __('Kombo') }}</small><b data-bp-combo>x1</b></span></div>
        <div class="bp-pill bp-burst" data-bp-burst title="{{ __('Pinch (jepit ibu jari + telunjuk) untuk ledakan') }}"><i class="ri-hand-heart-line" aria-hidden="true"></i><span><small>{{ __('Burst') }}</small><b data-bp-burst-state>{{ __('siap') }}</b></span></div>
    </div>

    <div class="bp-board hs-board" data-bp-board data-hs-board data-ar="0">
        @include('partials.hand-stage', ['guide' => [
            ['✋', __('Tunjukkan tangan'), __('Arahkan telapak ke kamera sampai terdeteksi.')],
            ['☝️', __('Gerakkan telunjuk'), __('Sentuh gelembung dengan ujung telunjuk untuk memecahkannya.')],
            ['🤏', __('Pinch untuk Burst'), __('Jepit ibu jari + telunjuk: ledakan area (jeda 3 detik).')],
        ]])

        <canvas class="bp-canvas" data-bp-canvas aria-label="{{ __('Area permainan Bubble Pop') }}"></canvas>
        <div class="bp-timebar" aria-hidden="true"><span data-bp-timebar></span></div>

        <div class="bp-corner" data-bp-corner hidden>
            <button type="button" class="bp-round" data-bp-pause data-dwell aria-label="{{ __('Jeda') }}" title="{{ __('Jeda (P)') }}"><i class="ri-pause-line" aria-hidden="true"></i></button>
        </div>
        <button type="button" class="bp-round bp-sound" data-bp-sound aria-label="{{ __('Suara') }}" title="{{ __('Suara (M)') }}"><i class="ri-volume-up-line" aria-hidden="true"></i></button>

        <div class="bp-overlay" data-bp-intro>
            <span class="bp-badge">🫧 Bubble Pop</span>
            <h3>{{ __('Siap memecahkan gelembung?') }}</h3>
            <p>{{ __('Biasa +1 · Emas ★ +5 · Gelap ✕ −3. Pecahkan beruntun untuk kombo hingga x3.') }}</p>
            <div class="bp-levels" role="radiogroup" aria-label="{{ __('Tingkat kesulitan') }}">
                <label data-dwell><input type="radio" name="bp-level" value="easy"><span><i class="ri-leaf-line" aria-hidden="true"></i><b>{{ __('Santai') }}</b><small>{{ __('Pelan & besar') }}</small></span></label>
                <label data-dwell><input type="radio" name="bp-level" value="normal" checked><span><i class="ri-bubble-chart-line" aria-hidden="true"></i><b>{{ __('Normal') }}</b><small>{{ __('Seimbang') }}</small></span></label>
                <label data-dwell><input type="radio" name="bp-level" value="hard"><span><i class="ri-fire-line" aria-hidden="true"></i><b>{{ __('Tantangan') }}</b><small>{{ __('Cepat & banyak') }}</small></span></label>
            </div>
            <button type="button" class="btn btn-primary bp-cta" data-bp-start data-dwell><i class="ri-play-fill" aria-hidden="true"></i> {{ __('Mulai') }}</button>
            <p class="hs-only muted">{{ __('Tunjuk tombol Mulai selama 1 detik atau pinch di atasnya.') }}</p>
            <p class="bp-keys muted"><kbd>Space</kbd> {{ __('mulai') }} · <kbd>P</kbd> {{ __('jeda') }} · <kbd>M</kbd> {{ __('suara') }}</p>
        </div>

        <div class="bp-overlay bp-count" data-bp-count hidden aria-live="assertive"><div class="bp-count-num" data-bp-count-num>3</div></div>

        <div class="bp-overlay" data-bp-pausemenu hidden>
            <h3>{{ __('Dijeda') }}</h3>
            <div class="bp-result-actions">
                <button type="button" class="btn btn-primary bp-cta" data-bp-resume data-dwell><i class="ri-play-fill" aria-hidden="true"></i> {{ __('Lanjut') }}</button>
                <button type="button" class="btn btn-secondary" data-bp-quit data-dwell>{{ __('Keluar ke menu') }}</button>
            </div>
        </div>

        <div class="bp-overlay" data-bp-result hidden>
            <div class="bp-record" data-bp-record hidden>🎉 {{ __('Rekor baru!') }}</div>
            <h3 data-bp-result-title>{{ __('Waktu habis!') }}</h3>
            <div class="bp-stars" data-bp-stars aria-hidden="true"><i>★</i><i>★</i><i>★</i></div>
            <div class="score-big" data-bp-final>0</div>
            <ul class="bp-stats">
                <li><small>{{ __('Gelembung pecah') }}</small><b data-bp-stat-popped>0</b></li>
                <li><small>{{ __('Kombo terbaik') }}</small><b data-bp-stat-combo>0</b></li>
                <li><small>{{ __('Level tercapai') }}</small><b data-bp-stat-level>1</b></li>
                <li><small>{{ __('Skor terbaik') }}</small><b data-bp-stat-best>—</b></li>
            </ul>
            <p class="muted" data-bp-save-status></p>
            <div class="bp-result-actions">
                <button type="button" class="btn btn-primary bp-cta" data-bp-again data-dwell><i class="ri-restart-line" aria-hidden="true"></i> {{ __('Main lagi') }}</button>
                <button type="button" class="btn btn-secondary" data-bp-menu data-dwell>{{ __('Ganti level') }}</button>
            </div>
        </div>
    </div>
</div>
@endsection
@push('scripts')<script type="module" src="{{ asset('js/bubble-pop.js') }}?v={{ filemtime(public_path('js/bubble-pop.js')) }}"></script>@endpush
