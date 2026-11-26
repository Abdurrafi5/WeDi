{{-- Panel kontrol kamera + preview (dipakai Bubble Pop & Hand Drawing). Video diproses lokal di browser. --}}
<div class="hand-controls" data-hand-controls>
    <button type="button" class="btn btn-secondary btn-sm" data-hand-toggle><i class="ri-camera-line" aria-hidden="true"></i> <span data-hand-toggle-label>{{ __('Aktifkan kamera') }}</span></button>
    <label class="ai-check"><input type="checkbox" data-hand-debug> {{ __('Mode uji (kerangka tangan & FPS)') }}</label>
    <span class="muted hand-status" data-hand-status role="status">{{ __('Kamera mati. Kamu tetap bisa bermain dengan mouse/sentuhan.') }}</span>
</div>
<p class="hand-privacy"><i class="ri-shield-check-line" aria-hidden="true"></i> {{ __('Kamera hanya menyala setelah kamu mengizinkannya. Video diproses di perangkatmu dan tidak pernah dikirim atau disimpan di server.') }}</p>
