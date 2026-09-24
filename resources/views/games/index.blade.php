@extends('layouts.app')
@section('title', __('Mini Game — WeDi'))
@section('content')
<div class="page-head"><div><h1>{{ __('Mini Game') }}</h1><p>{{ __('Jeda santai di tengah harimu.') }}</p></div></div>

<div class="game-grid" style="margin-bottom:26px;">
    <div class="game-card">
        <div class="g-icon"></div>
        <h3>{{ __('Memory Match') }}</h3>
        <p class="g-best">{{ __('Best score') }}: <span id="best-memory-match">{{ $bestScores['memory-match'] ?? '—' }}</span></p>
        <a href="#game-memory-match" class="btn btn-secondary btn-sm">{{ __('Main') }}</a>
    </div>
    <div class="game-card">
        <div class="g-icon"></div>
        <h3>{{ __('Zen Tap') }}</h3>
        <p class="g-best">{{ __('Best score') }}: <span id="best-zen-tap">{{ $bestScores['zen-tap'] ?? '—' }}</span></p>
        <a href="#game-zen-tap" class="btn btn-secondary btn-sm">{{ __('Main') }}</a>
    </div>
    <div class="game-card">
        <div class="g-icon"></div>
        <h3>{{ __('Word Bloom') }}</h3>
        <p class="g-best">{{ __('Best score') }}: <span id="best-word-bloom">{{ $bestScores['word-bloom'] ?? '—' }}</span></p>
        <a href="#game-word-bloom" class="btn btn-secondary btn-sm">{{ __('Main') }}</a>
    </div>
</div>

{{-- Memory Match --}}
<div class="card" id="game-memory-match" style="margin-bottom:22px;">
    <h3> {{ __('Memory Match') }}</h3>
    <div class="game-stage game-intro active" style="text-align:center;">
        <p>{{ __('Cocokkan 6 pasangan simbol dalam 60 detik.') }}</p>
        <button type="button" class="btn btn-primary" data-start>{{ __('Mulai') }}</button>
    </div>
    <div class="game-stage game-play">
        <div class="game-hud"><span><i class="ri-timer-line"></i> <span data-timer>60s</span></span><span data-pairs>0 / 6</span></div>
        <div class="memory-grid"></div>
    </div>
    <div class="game-stage game-result">
        <div style="text-align:center;">
            <p>{{ __('Skor kamu') }}</p>
            <div class="score-big">0</div>
            <p class="g-best">{{ __('Best score') }}: <span data-best-score>{{ $bestScores['memory-match'] ?? '—' }}</span></p>
            <button type="button" class="btn btn-primary" data-restart>{{ __('Main Lagi') }}</button>
        </div>
    </div>
</div>

{{-- Zen Tap --}}
<div class="card" id="game-zen-tap" style="margin-bottom:22px;">
    <h3> {{ __('Zen Tap') }}</h3>
    <div class="game-stage game-intro active" style="text-align:center;">
        <p>{{ __('Ketuk target sebanyak mungkin dalam 20 detik.') }}</p>
        <button type="button" class="btn btn-primary" data-start>{{ __('Mulai') }}</button>
    </div>
    <div class="game-stage game-play">
        <div class="game-hud"><span><i class="ri-timer-line"></i> <span data-timer>20s</span></span><span> <span data-taps>0</span></span></div>
        <div class="zen-field"></div>
    </div>
    <div class="game-stage game-result">
        <div style="text-align:center;">
            <p>{{ __('Total tap') }}</p>
            <div class="score-big">0</div>
            <p class="g-best">{{ __('Best score') }}: <span data-best-score>{{ $bestScores['zen-tap'] ?? '—' }}</span></p>
            <button type="button" class="btn btn-primary" data-restart>{{ __('Main Lagi') }}</button>
        </div>
    </div>
</div>

{{-- Word Bloom --}}
<div class="card" id="game-word-bloom">
    <h3> {{ __('Word Bloom') }}</h3>
    <div class="game-stage game-intro active" style="text-align:center;">
        <p>{{ __('Susun huruf menjadi kata yang benar dalam 5 ronde.') }}</p>
        <button type="button" class="btn btn-primary" data-start>{{ __('Mulai') }}</button>
    </div>
    <div class="game-stage game-play">
        <div class="game-hud"><span>{{ __('Ronde') }} <span data-round>1 / 5</span></span><span>{{ __('Skor') }} <span data-score>0</span></span></div>
        <p data-hint style="text-align:center;font-style:italic;color:var(--text-muted);"></p>
        <div class="bloom-answer" data-answer></div>
        <div class="bloom-letters"></div>
        <div style="display:flex;gap:10px;justify-content:center;">
            <button type="button" class="btn btn-secondary btn-sm" data-clear>{{ __('Hapus') }}</button>
            <button type="button" class="btn btn-primary btn-sm" data-submit>{{ __('Submit') }}</button>
        </div>
    </div>
    <div class="game-stage game-result">
        <div style="text-align:center;">
            <p>{{ __('Skor akhir') }}</p>
            <div class="score-big">0</div>
            <p class="g-best">{{ __('Best score') }}: <span data-best-score>{{ $bestScores['word-bloom'] ?? '—' }}</span></p>
            <button type="button" class="btn btn-primary" data-restart>{{ __('Main Lagi') }}</button>
        </div>
    </div>
</div>
@endsection
