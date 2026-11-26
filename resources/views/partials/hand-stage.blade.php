{{-- Lapisan di dalam board: preview kamera (PiP/AR), efek tangan, petunjuk, panduan. --}}
<div class="hs-cam" data-hs-cam data-filter="natural" hidden>
    <video data-hs-video playsinline muted></video>
    <span class="hs-cam-label"><i class="hs-dot" aria-hidden="true"></i> LIVE</span>
</div>
<canvas class="hs-fx" data-hs-fx aria-hidden="true"></canvas>
<div class="hs-hint" data-hs-hint hidden><i class="ri-hand-heart-line" aria-hidden="true"></i> {{ __('Tangan tidak terlihat. Dekatkan ke kamera dan pastikan cahaya cukup.') }}</div>
<div class="hs-guide" data-hs-guide hidden role="dialog" aria-label="{{ __('Panduan hand tracking') }}">
    <div class="hs-guide-card">
        <h3>{{ $guideTitle ?? __('Cara bermain dengan tanganmu') }}</h3>
        <ol class="hs-steps">
            @foreach($guide as $i => $step)
            <li style="--i:{{ $i }}"><span class="hs-emoji hs-emoji-{{ $i }}" aria-hidden="true">{{ $step[0] }}</span><strong>{{ $step[1] }}</strong><small>{{ $step[2] }}</small></li>
            @endforeach
        </ol>
        <p class="hs-tip"><i class="ri-lightbulb-flash-line" aria-hidden="true"></i> {{ __('Tips: cahaya cukup, tangan 30–60 cm dari kamera, latar tidak ramai. Tombol bisa "diklik" dengan menunjuknya 1 detik atau pinch.') }}</p>
        <button type="button" class="btn btn-primary" data-hs-guide-ok data-dwell>{{ __('Mengerti, ayo mulai!') }}</button>
    </div>
</div>
