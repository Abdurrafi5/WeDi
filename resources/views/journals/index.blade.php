@extends('layouts.app')
@section('title', __('Jurnal Mingguan — WeDi'))
@section('content')
<div class="page-head">
    <div>
        <p class="page-kicker">{{ __('Weekly diary') }}</p>
        <h1>{{ __('Jurnal minggu ini') }}</h1>
        <p>{{ $diary->range_label }} · {{ __('Pilih hari, lalu tulis tanpa mengisi tanggal manual.') }}</p>
    </div>
    <a href="{{ route('journal.create', ['day' => now()->toDateString()]) }}" class="btn btn-primary">
        <i class="ri-quill-pen-line" aria-hidden="true"></i> {{ __('Tulis hari ini') }}
    </a>
</div>

<div class="card week-board journal-week-board">
    <div class="section-line">
        <div>
            <p class="eyebrow">{{ __('Senin sampai Minggu') }}</p>
            <h3>{{ __('Pilih hari yang ingin kamu simpan') }}</h3>
        </div>
        <span class="muted">{{ $journals->count() }}/7 {{ __('hari terisi') }}</span>
    </div>
    <div class="week-strip">
        @foreach($weekDays as $day)
        <a href="#day-{{ $day['date']->format('Y-m-d') }}" class="week-day {{ $day['is_today'] ? 'is-today' : '' }} {{ $day['entry'] ? 'has-entry' : '' }}">
            <span>{{ $day['date']->isoFormat('ddd') }}</span>
            <strong>{{ $day['date']->format('d') }}</strong>
            <small>{{ $day['entry'] ? __('Sudah ada') : __('Belum ada') }}</small>
        </a>
        @endforeach
    </div>
</div>

<div class="journal-week-list">
    @foreach($weekDays as $day)
    <article id="day-{{ $day['date']->format('Y-m-d') }}" class="day-journal card {{ $day['is_today'] ? 'today' : '' }}">
        <div class="day-journal-date">
            <span>{{ $day['date']->isoFormat('dddd') }}</span>
            <strong>{{ $day['date']->format('d') }}</strong>
            <small>{{ $day['date']->translatedFormat('F Y') }}</small>
        </div>
        <div class="day-journal-content">
            @if($day['entry'])
            <p class="eyebrow">{{ $day['entry']->mood_label ?: __('Catatan tersimpan') }}</p>
            <h3>{{ $day['entry']->title ?: __('Tanpa judul') }}</h3>
            <p>{{ \Illuminate\Support\Str::limit($day['entry']->content, 260) }}</p>
            <div class="je-actions">
                <a href="{{ route('journal.edit', $day['entry']->id) }}" class="btn btn-secondary btn-sm">
                    <i class="ri-edit-line" aria-hidden="true"></i> {{ __('Edit tulisan') }}
                </a>
            </div>
            @else
            <p class="eyebrow">{{ $day['is_today'] ? __('Hari ini') : __('Hari yang belum ditulis') }}</p>
            <h3>{{ __('Belum ada cerita di sini.') }}</h3>
            <p>{{ __('Simpan satu momen kecil dari hari ini agar minggu terasa lebih utuh.') }}</p>
            <div class="je-actions">
                <a href="{{ route('journal.create', ['day' => $day['date']->toDateString()]) }}" class="btn btn-primary btn-sm">
                    <i class="ri-quill-pen-line" aria-hidden="true"></i> {{ __('Tulis untuk hari ini') }}
                </a>
            </div>
            @endif
        </div>
    </article>
    @endforeach
</div>
@endsection
