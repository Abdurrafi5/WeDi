@extends('layouts.app')
@section('title', __('Week recap — WeDi'))
@section('content')
<div class="dashboard-page history-recap-page">
    <div class="page-head">
        <div>
            <p class="page-kicker">{{ __('Archive') }}</p>
            <h1>{{ __('Week recap') }}</h1>
            <p>{{ $diary->range_label }}</p>
        </div>
        <a href="{{ route('history.index') }}" class="btn btn-secondary">
            <i class="ri-arrow-left-line" aria-hidden="true"></i> {{ __('Back to history') }}
        </a>
    </div>

    <div class="recap-stat-row">
        <div class="stat-card">
            <i class="ri-book-open-line recap-stat-icon" aria-hidden="true"></i>
            <div class="stat-label">{{ __('Journals') }}</div>
            <div class="stat-value">{{ $journalCount }}<small>/7</small></div>
        </div>
        <div class="stat-card">
            <i class="ri-checkbox-circle-line recap-stat-icon" aria-hidden="true"></i>
            <div class="stat-label">{{ __('Targets done') }}</div>
            <div class="stat-value">{{ $completedCount }}<small>/{{ $totalTargets }}</small></div>
        </div>
        <div class="stat-card">
            <i class="ri-bar-chart-line recap-stat-icon" aria-hidden="true"></i>
            <div class="stat-label">{{ __('Progress') }}</div>
            <div class="stat-value">{{ $progress }}%</div>
        </div>
    </div>

    @php
        $firstWithEntry = collect($weekDays)->first(fn($d) => $d['journal']);
        $requested = collect($weekDays)->first(fn($d) => $d['date']->toDateString() === request('day'));
        $selectedDate = ($requested ?? $firstWithEntry ?? $weekDays->first())['date']->toDateString();
    @endphp

    <section class="card history-week-snapshot" id="journal-reader">
        <div class="panel-heading">
            <div>
                <span class="panel-kicker">{{ __('Weekly rhythm') }}</span>
                <h2>{{ __('Seven days, one story') }}</h2>
                <p class="muted">{{ __('Tap a day to read its journal.') }}</p>
            </div>
            <span class="badge {{ $diary->status === 'active' ? 'badge-low' : 'badge-medium' }}">{{ $diary->status === 'active' ? __('Active') : __('Completed') }}</span>
        </div>

        <div class="recap-week-strip" role="tablist">
            @foreach($weekDays as $day)
            @php($key = $day['date']->toDateString())
            <a href="?day={{ $key }}#journal-reader" role="tab" data-day-tab="{{ $key }}" aria-selected="{{ $key === $selectedDate ? 'true' : 'false' }}" class="recap-day {{ $day['journal'] ? 'has-entry' : '' }} {{ $key === $selectedDate ? 'is-selected' : '' }}">
                <span>{{ $day['date']->isoFormat('ddd') }}</span>
                <strong>{{ $day['date']->format('d') }}</strong>
                <i class="{{ $day['journal'] ? 'ri-check-line' : 'ri-subtract-line' }}" aria-hidden="true"></i>
            </a>
            @endforeach
        </div>

        @foreach($weekDays as $day)
        @php($key = $day['date']->toDateString())
        @php($j = $day['journal'])
        <article class="day-reader" data-day-panel="{{ $key }}" role="tabpanel" @if($key !== $selectedDate) hidden @endif>
            <header class="day-reader-head">
                <div>
                    <span class="panel-kicker">{{ $day['date']->translatedFormat('l') }}</span>
                    <h3>{{ $day['date']->translatedFormat('j F Y') }}</h3>
                </div>
                @if($j && $j->mood)
                <span class="mood-chip"><i class="{{ $j->mood_emoji }}" aria-hidden="true"></i>{{ $j->mood_label }}</span>
                @endif
            </header>
            @if($j)
            <h4 class="day-reader-title">{{ $j->title ?: __('Tanpa judul') }}</h4>
            <div class="day-reader-body">{!! nl2br(e($j->content)) !!}</div>
            <div class="day-reader-actions">
                <a href="{{ route('journal.edit', $j->id) }}" class="btn btn-secondary btn-sm">
                    <i class="ri-edit-line" aria-hidden="true"></i> {{ __('Edit tulisan') }}
                </a>
            </div>
            @else
            <div class="day-reader-empty">
                <i class="ri-quill-pen-line" aria-hidden="true"></i>
                <h4>{{ __('No journal on this day.') }}</h4>
                <p>{{ __('You did not write anything on this day.') }}</p>
            </div>
            @endif
        </article>
        @endforeach
    </section>

    @if(!$recap)
    <div class="card history-empty" style="margin-top:16px">
        <i class="ri-file-warning-line" aria-hidden="true"></i>
        <h2>{{ __('No recap for this week yet.') }}</h2>
        <p>{{ __('This week has journals and targets, but no written reflection was saved.') }}</p>
    </div>
    @else
    <div class="history-reflection-grid">
        <div class="reflection-card reflection-achievement">
            <label><i class="ri-trophy-line" aria-hidden="true"></i>{{ __('Achievement') }}</label>
            <p>{{ $recap->achievement ?: '—' }}</p>
        </div>
        <div class="reflection-card reflection-reflection">
            <label><i class="ri-chat-quote-line" aria-hidden="true"></i>{{ __('Reflection') }}</label>
            <p>{{ $recap->reflection ?: '—' }}</p>
        </div>
        <div class="reflection-card reflection-lesson">
            <label><i class="ri-lightbulb-line" aria-hidden="true"></i>{{ __('What I learned') }}</label>
            <p>{{ $recap->lesson_learned ?: '—' }}</p>
        </div>
        <div class="reflection-card reflection-plan">
            <label><i class="ri-route-line" aria-hidden="true"></i>{{ __('Next week plan') }}</label>
            <p>{{ $recap->next_week_plan ?: '—' }}</p>
        </div>
    </div>
    @endif
</div>
@endsection
