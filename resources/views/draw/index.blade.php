@extends('layouts.app')
@section('title', __('Studio Gambar — WeDi'))
@section('content')
<div class="page-head">
    <div>
        <p class="page-kicker">{{ __('Creative Space') }}</p>
        <h1>{{ __('Hand Gesture Drawing') }}</h1>
        <p>{{ __('Menggambar dengan gerakan tangan: jepit ibu jari + telunjuk untuk melukis, lepas untuk mengangkat kuas.') }}</p>
    </div>
    <a href="{{ route('draw.gallery') }}" class="btn btn-secondary"><i class="ri-gallery-line" aria-hidden="true"></i> {{ __('Galeri karyaku') }}</a>
</div>

<div class="card draw hs-root" id="draw-studio"
     data-store-url="{{ route('draw.store') }}"
     data-gallery-url="{{ route('draw.gallery') }}"
     @if($artwork) data-artwork-id="{{ $artwork->id }}" data-artwork-image="{{ route('draw.image', $artwork->id) }}" data-update-url="{{ route('draw.update', $artwork->id) }}" @endif>
    @include('partials.hand-bar', ['ar' => false])

    <div class="draw-layout">
        <aside class="draw-dock" aria-label="{{ __('Alat menggambar') }}">
            <section class="dock-sec">
                <h4>{{ __('Kuas') }}</h4>
                <div class="dock-brushes" role="group">
                    <button type="button" class="dock-btn active" data-brush="pen" aria-pressed="true"><i class="ri-pen-nib-line" aria-hidden="true"></i><span>{{ __('Pena') }}</span></button>
                    <button type="button" class="dock-btn" data-brush="marker" aria-pressed="false"><i class="ri-mark-pen-line" aria-hidden="true"></i><span>{{ __('Marker') }}</span></button>
                    <button type="button" class="dock-btn" data-brush="neon" aria-pressed="false"><i class="ri-flashlight-line" aria-hidden="true"></i><span>Neon</span></button>
                    <button type="button" class="dock-btn" data-brush="rainbow" aria-pressed="false"><i class="ri-rainbow-line" aria-hidden="true"></i><span>{{ __('Pelangi') }}</span></button>
                </div>
                <button type="button" class="dock-btn dock-eraser" data-tool-eraser aria-pressed="false"><i class="ri-eraser-line" aria-hidden="true"></i><span>{{ __('Penghapus') }}</span></button>
                <p class="dock-tip" data-draw-tip hidden>{{ __('Neon paling cantik di latar gelap — coba latar “Malam”.') }}</p>
            </section>
            <section class="dock-sec">
                <h4>{{ __('Warna') }}</h4>
                <div class="dock-colors">
                    @foreach(['#3e4b8e','#b8467a','#e8554e','#f59e42','#f6d44a','#5cbf8a','#3dbfd9','#8a5cf5','#3d1534','#8b8b8b','#f2a7c3','#ffffff'] as $c)
                    <button type="button" class="draw-swatch" style="--c:{{ $c }}" data-swatch="{{ $c }}" aria-label="{{ __('Warna') }} {{ $c }}"></button>
                    @endforeach
                </div>
                <div class="dock-custom">
                    <label class="draw-color" title="{{ __('Pilih warna sendiri') }}"><input type="color" value="#3e4b8e" data-color aria-label="{{ __('Warna kustom') }}"></label>
                    <div class="dock-recent" data-recent aria-label="{{ __('Warna terakhir') }}"></div>
                </div>
            </section>
            <section class="dock-sec">
                <h4>{{ __('Ukuran') }} <b data-size-label>8</b></h4>
                <div class="dock-size"><input type="range" min="2" max="60" value="8" data-size aria-label="{{ __('Ukuran kuas') }}"><span class="dock-dot" data-size-dot></span></div>
                <h4>{{ __('Opasitas') }} <b data-opacity-label>100%</b></h4>
                <input type="range" min="10" max="100" value="100" data-opacity aria-label="{{ __('Opasitas') }}">
            </section>
            <section class="dock-sec">
                <h4>{{ __('Latar') }}</h4>
                <div class="dock-bgs" role="group">
                    <button type="button" class="dock-bg active" data-bg="white" style="--bg:#ffffff" aria-label="{{ __('Putih') }}" title="{{ __('Putih') }}"></button>
                    <button type="button" class="dock-bg" data-bg="cream" style="--bg:#fbf1dd" aria-label="{{ __('Krem') }}" title="{{ __('Krem') }}"></button>
                    <button type="button" class="dock-bg" data-bg="night" style="--bg:#14101f" aria-label="{{ __('Malam') }}" title="{{ __('Malam') }}"></button>
                    <button type="button" class="dock-bg" data-bg="grid" style="--bg:#ffffff" aria-label="{{ __('Kotak-kotak') }}" title="{{ __('Kotak-kotak') }}"></button>
                </div>
            </section>
            <section class="dock-sec dock-actions">
                <button type="button" class="btn btn-secondary btn-sm" data-undo><i class="ri-arrow-go-back-line" aria-hidden="true"></i> Undo</button>
                <button type="button" class="btn btn-secondary btn-sm" data-redo><i class="ri-arrow-go-forward-line" aria-hidden="true"></i> Redo</button>
                <button type="button" class="btn btn-ghost btn-sm" data-clear><i class="ri-delete-bin-line" aria-hidden="true"></i> {{ __('Bersihkan') }}</button>
            </section>
        </aside>

        <div class="draw-main">
            <div class="draw-board hs-board" data-draw-board data-hs-board data-bg="white" data-ar="0">
                <canvas class="draw-canvas" data-draw-canvas aria-label="{{ __('Kanvas gambar') }}"></canvas>
                <canvas class="draw-stroke" data-draw-stroke aria-hidden="true"></canvas>
                <canvas class="draw-cursor" data-draw-cursor aria-hidden="true"></canvas>
                @include('partials.hand-stage', ['guide' => [
                    ['✋', __('Tunjukkan tangan'), __('Arahkan telapak ke kamera sampai terdeteksi.')],
                    ['🤏', __('Pinch untuk melukis'), __('Jepit ibu jari + telunjuk = kuas turun. Lepas = kuas naik.')],
                    ['☝️', __('Tunjuk palet'), __('Tunjuk ikon di palet atas selama 1 detik untuk ganti warna, undo, dll.')],
                ], 'guideTitle' => __('Menggambar dengan tangan')])

                <div class="dh-palette" data-dh-palette>
                    <button type="button" class="dh-btn" data-dh="undo" data-dwell aria-label="Undo"><i class="ri-arrow-go-back-line" aria-hidden="true"></i></button>
                    <button type="button" class="dh-btn" data-dh="redo" data-dwell aria-label="Redo"><i class="ri-arrow-go-forward-line" aria-hidden="true"></i></button>
                    <button type="button" class="dh-btn" data-dh="eraser" data-dwell aria-label="{{ __('Penghapus') }}"><i class="ri-eraser-line" aria-hidden="true"></i></button>
                    @foreach(['#3e4b8e','#e8554e','#f6d44a','#5cbf8a','#8a5cf5','#3d1534'] as $c)
                    <button type="button" class="dh-btn dh-color" style="--c:{{ $c }}" data-dh-color="{{ $c }}" data-dwell aria-label="{{ __('Warna') }} {{ $c }}"></button>
                    @endforeach
                    <button type="button" class="dh-btn" data-dh="smaller" data-dwell aria-label="{{ __('Kecilkan') }}"><i class="ri-subtract-line" aria-hidden="true"></i></button>
                    <button type="button" class="dh-btn" data-dh="bigger" data-dwell aria-label="{{ __('Besarkan') }}"><i class="ri-add-line" aria-hidden="true"></i></button>
                    <button type="button" class="dh-btn dh-danger" data-dh="clear" data-dwell data-dwell-ms="1800" aria-label="{{ __('Bersihkan') }}"><i class="ri-delete-bin-line" aria-hidden="true"></i></button>
                    <button type="button" class="dh-btn dh-save" data-dh="save" data-dwell data-dwell-ms="1400" aria-label="{{ __('Simpan') }}"><i class="ri-save-line" aria-hidden="true"></i></button>
                </div>
                <div class="draw-toast" data-draw-toast hidden role="status"></div>
            </div>

            <div class="draw-save">
                <input class="input" type="text" maxlength="120" placeholder="{{ __('Judul karya (opsional)') }}" data-title value="{{ $artwork->title ?? '' }}">
                <button type="button" class="btn btn-primary" data-save><i class="ri-save-line" aria-hidden="true"></i> {{ $artwork ? __('Simpan perubahan') : __('Simpan karya') }}</button>
                @if($artwork)<button type="button" class="btn btn-secondary" data-save-new>{{ __('Simpan sebagai baru') }}</button>@endif
                <a class="btn btn-ghost" href="#" data-download download="wedi-artwork.png"><i class="ri-download-2-line" aria-hidden="true"></i> {{ __('Unduh PNG') }}</a>
            </div>
            <p class="hs-privacy"><i class="ri-lock-line" aria-hidden="true"></i> {{ __('Karya hanya disimpan ke galerimu kalau kamu menekan Simpan.') }}</p>
        </div>
    </div>
</div>
@endsection
@push('scripts')<script type="module" src="{{ asset('js/drawing.js') }}?v={{ filemtime(public_path('js/drawing.js')) }}"></script>@endpush
