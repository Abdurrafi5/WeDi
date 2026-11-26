{{-- Bar kontrol kamera + pengaturan "hand filter" (dipakai Bubble Pop & Drawing). Video diproses lokal. --}}
@php
    $handStyles = ['neon' => ['Neon', 'ri-flashlight-line'], 'aurora' => ['Aurora', 'ri-rainbow-line'], 'sakura' => ['Sakura', 'ri-flower-line'], 'minimal' => ['Minimal', 'ri-contrast-2-line']];
    $handFilters = ['natural' => __('Natural'), 'soft' => __('Lembut'), 'dream' => 'Dream', 'noir' => 'Noir', 'neon' => 'Neon'];
@endphp
<div class="hs-bar" data-hs-bar>
    <button type="button" class="btn btn-primary hs-toggle" data-hs-toggle>
        <i class="ri-camera-line" aria-hidden="true"></i><span data-hs-toggle-label>{{ __('Aktifkan kamera') }}</span>
    </button>
    <span class="hs-status" data-hs-status data-state="off" role="status"><i class="hs-dot" aria-hidden="true"></i><span data-hs-status-text>{{ __('Kamera mati') }}</span></span>
    <span class="hs-chip" data-hs-gesture hidden></span>
    <span class="hs-chip hs-fps" data-hs-fps hidden></span>
    <span class="hs-spacer"></span>
    <button type="button" class="icon-btn hs-icon" data-hs-help aria-label="{{ __('Panduan') }}" title="{{ __('Panduan') }}"><i class="ri-question-line" aria-hidden="true"></i></button>
    <div class="hs-settings-wrap">
        <button type="button" class="icon-btn hs-icon" data-hs-settings-btn aria-expanded="false" aria-label="{{ __('Pengaturan tangan') }}" title="{{ __('Pengaturan tangan') }}"><i class="ri-equalizer-3-line" aria-hidden="true"></i></button>
        <div class="hs-settings" data-hs-settings hidden>
            <div class="hs-set-group">
                <span class="hs-set-title">{{ __('Gaya efek tangan') }}</span>
                <div class="hs-seg" data-hs-style>
                    @foreach($handStyles as $key => [$label, $icon])
                    <button type="button" data-value="{{ $key }}"><i class="{{ $icon }}" aria-hidden="true"></i>{{ $label }}</button>
                    @endforeach
                </div>
            </div>
            <div class="hs-set-group">
                <span class="hs-set-title">{{ __('Filter kamera') }}</span>
                <div class="hs-seg hs-seg-wrap" data-hs-filter>
                    @foreach($handFilters as $key => $label)
                    <button type="button" data-value="{{ $key }}" class="hs-f-{{ $key }}">{{ $label }}</button>
                    @endforeach
                </div>
            </div>
            <div class="hs-set-group">
                <span class="hs-set-title">{{ __('Kehalusan ↔ Responsif') }}</span>
                <input type="range" min="0" max="100" value="55" data-hs-sens aria-label="{{ __('Sensitivitas gerak tangan') }}">
            </div>
            <div class="hs-set-group hs-toggles">
                <label class="hs-switch"><input type="checkbox" data-hs-trail><span></span>{{ __('Jejak jari & percikan') }}</label>
                @if(! empty($ar))
                <label class="hs-switch"><input type="checkbox" data-hs-ar><span></span>{{ __('Kamera sebagai latar (AR)') }}</label>
                @endif
                <label class="hs-switch"><input type="checkbox" data-hs-debug><span></span>{{ __('Mode uji (nomor sendi & FPS)') }}</label>
            </div>
            <button type="button" class="btn btn-ghost btn-sm" data-hs-reset-guide><i class="ri-lightbulb-flash-line" aria-hidden="true"></i> {{ __('Lihat panduan lagi') }}</button>
        </div>
    </div>
</div>
<p class="hs-privacy"><i class="ri-shield-check-line" aria-hidden="true"></i> {{ __('Kamera hanya menyala setelah kamu mengizinkannya. Video diproses di perangkatmu dan tidak pernah dikirim atau disimpan di server.') }}</p>
