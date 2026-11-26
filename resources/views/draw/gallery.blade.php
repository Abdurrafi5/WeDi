@extends('layouts.app')
@section('title', __('Galeri Karya — WeDi'))
@section('content')
<div class="page-head">
    <div>
        <p class="page-kicker">{{ __('Creative Space') }}</p>
        <h1>{{ __('Galeri karyaku') }}</h1>
        <p>{{ __('Hanya kamu yang bisa melihat karya ini.') }}</p>
    </div>
    <a href="{{ route('draw.index') }}" class="btn btn-primary"><i class="ri-paint-brush-line" aria-hidden="true"></i> {{ __('Gambar baru') }}</a>
</div>

@if($artworks->isEmpty())
<div class="card ai-empty"><i class="ri-palette-line" aria-hidden="true"></i><h3>{{ __('Belum ada karya') }}</h3><p>{{ __('Buat gambar pertamamu dengan gerakan tangan, lalu simpan di sini.') }}</p><a class="btn btn-primary" href="{{ route('draw.index') }}">{{ __('Mulai menggambar') }}</a></div>
@else
<div class="gallery-grid">
    @foreach($artworks as $a)
    <figure class="card gallery-item" data-gallery-item data-src="{{ route('draw.image', $a->id) }}" data-title="{{ $a->title ?: __('Tanpa judul') }}" data-edit="{{ route('draw.index', ['artwork' => $a->id]) }}" data-download="{{ route('draw.image', ['artwork' => $a->id, 'download' => 1]) }}">
        <button type="button" class="gallery-thumb" data-gallery-open aria-label="{{ __('Lihat karya') }}: {{ $a->title ?: __('Tanpa judul') }}">
            <img src="{{ route('draw.image', $a->id) }}" alt="{{ $a->title ?: __('Karya tanpa judul') }}" loading="lazy">
            <span class="gallery-zoom"><i class="ri-zoom-in-line" aria-hidden="true"></i></span>
        </button>
        <figcaption>
            <div><strong>{{ $a->title ?: __('Tanpa judul') }}</strong><small>{{ $a->created_at->translatedFormat('d M Y') }} · {{ $a->width }}×{{ $a->height }}</small></div>
            <div class="gallery-actions">
                <a class="icon-btn" href="{{ route('draw.index', ['artwork' => $a->id]) }}" aria-label="{{ __('Buka kembali') }}" title="{{ __('Buka kembali') }}"><i class="ri-edit-line" aria-hidden="true"></i></a>
                <a class="icon-btn" href="{{ route('draw.image', ['artwork' => $a->id, 'download' => 1]) }}" aria-label="{{ __('Unduh') }}" title="{{ __('Unduh') }}"><i class="ri-download-2-line" aria-hidden="true"></i></a>
                <form method="POST" action="{{ route('draw.destroy', $a->id) }}" data-confirm="{{ __('Hapus karya ini?') }}">@csrf @method('DELETE')<button type="submit" class="icon-btn danger" aria-label="{{ __('Hapus') }}" title="{{ __('Hapus') }}"><i class="ri-delete-bin-line" aria-hidden="true"></i></button></form>
            </div>
        </figcaption>
    </figure>
    @endforeach
</div>
<div class="lightbox" data-lightbox hidden role="dialog" aria-modal="true" aria-label="{{ __('Pratinjau karya') }}">
    <button type="button" class="lightbox-close icon-btn" data-lightbox-close aria-label="{{ __('Tutup') }}"><i class="ri-close-line" aria-hidden="true"></i></button>
    <button type="button" class="lightbox-nav prev" data-lightbox-prev aria-label="{{ __('Sebelumnya') }}"><i class="ri-arrow-left-s-line" aria-hidden="true"></i></button>
    <figure class="lightbox-fig"><img alt="" data-lightbox-img><figcaption><strong data-lightbox-title></strong><span><a class="btn btn-secondary btn-sm" data-lightbox-edit href="#"><i class="ri-edit-line" aria-hidden="true"></i> {{ __('Buka kembali') }}</a> <a class="btn btn-primary btn-sm" data-lightbox-download href="#"><i class="ri-download-2-line" aria-hidden="true"></i> {{ __('Unduh') }}</a></span></figcaption></figure>
    <button type="button" class="lightbox-nav next" data-lightbox-next aria-label="{{ __('Berikutnya') }}"><i class="ri-arrow-right-s-line" aria-hidden="true"></i></button>
</div>
@if($artworks->hasPages())<div class="gallery-pager">@if($artworks->previousPageUrl())<a class="btn btn-secondary btn-sm" href="{{ $artworks->previousPageUrl() }}">← {{ __('Sebelumnya') }}</a>@endif<span class="muted">{{ $artworks->currentPage() }} / {{ $artworks->lastPage() }}</span>@if($artworks->nextPageUrl())<a class="btn btn-secondary btn-sm" href="{{ $artworks->nextPageUrl() }}">{{ __('Berikutnya') }} →</a>@endif</div>@endif
@endif
@endsection
@push('scripts')<script defer src="{{ asset('js/ai.js') }}?v={{ filemtime(public_path('js/ai.js')) }}"></script><script defer src="{{ asset('js/gallery.js') }}?v={{ filemtime(public_path('js/gallery.js')) }}"></script>@endpush
