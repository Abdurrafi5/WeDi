@extends('layouts.app')
@section('title', __('History — WeDi'))
@section('content')
<div class="dashboard-page history-page">
    <div class="page-head">
        <div>
            <p class="page-kicker">{{ __('Your archive') }}</p>
            <h1>{{ __('Week history') }}</h1>
            <p>{{ __('Review your past weeks and the small steps inside them.') }}</p>
        </div>
        <span class="history-total"><i class="ri-calendar-schedule-line" aria-hidden="true"></i> {{ $diaries->count() }} {{ __('weeks') }}</span>
    </div>

    @if($diaries->isEmpty())
    <div class="card history-empty">
        <i class="ri-archive-line" aria-hidden="true"></i>
        <h2>{{ __('Your story starts here.') }}</h2>
        <p>{{ __('Write your first journal or set a target this week. It will appear here when the week is ready to remember.') }}</p>
        <a href="{{ route('dashboard') }}" class="btn btn-primary">{{ __('Go to dashboard') }}</a>
    </div>
    @else
    <div class="history-timeline">
        @foreach($diaries as $diary)
        @php
            $progress = $diary->targets_count ? (int) round($diary->completed_targets_count / $diary->targets_count * 100) : 0;
            $entryDates = $diary->journals->map(fn($j) => $j->entry_date->toDateString())->all();
        @endphp
        <article class="history-card card">
            <div class="history-card-main">
                <div class="history-card-head">
                    <div>
                        <span class="panel-kicker">{{ $diary->status === 'active' ? __('Current week') : __('Completed week') }}</span>
                        <h2>{{ $diary->range_label }}</h2>
                    </div>
                    <span class="badge {{ $diary->status === 'active' ? 'badge-low' : 'badge-medium' }}">{{ $diary->status === 'active' ? __('Active') : __('Completed') }}</span>
                </div>

                <div class="history-progress-line">
                    <div class="progress-bar"><span style="width:{{ $progress }}%"></span></div>
                    <strong>{{ $progress }}%</strong>
                </div>

                <div class="history-days" aria-label="{{ __('Days with a journal') }}">
                    @for($i = 0; $i < 7; $i++)
                    @php($d = $diary->week_start->copy()->addDays($i))
                    <a href="{{ route('history.recap', $diary->id) }}?day={{ $d->toDateString() }}#journal-reader" class="hd {{ in_array($d->toDateString(), $entryDates) ? 'has-entry' : '' }}" title="{{ $d->translatedFormat('l, j F') }}">
                        <span>{{ $d->isoFormat('dd') }}</span>
                    </a>
                    @endfor
                </div>

                <div class="history-stats">
                    <span><i class="ri-book-open-line" aria-hidden="true"></i> {{ $diary->journals_count }} {{ __('journal entries') }}</span>
                    <span><i class="ri-checkbox-circle-line" aria-hidden="true"></i> {{ $diary->completed_targets_count }}/{{ $diary->targets_count }} {{ __('targets') }}</span>
                    <span><i class="ri-file-text-line" aria-hidden="true"></i> {{ $diary->recap ? __('Recap saved') : __('No recap yet') }}</span>
                </div>
            </div>
            <a href="{{ route('history.recap', $diary->id) }}" class="history-open">
                <i class="ri-arrow-right-line" aria-hidden="true"></i>
                <span>{{ __('Open week') }}</span>
            </a>
        </article>
        @endforeach
    </div>
    @endif
</div>
@endsection
