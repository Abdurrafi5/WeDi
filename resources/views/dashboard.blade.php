@extends('layouts.app')
@section('title', __('Dashboard — WeDi'))
@section('content')
@php
    $moodColors = [
        'senang' => 'var(--brand)',
        'semangat' => '#477B60',
        'biasa' => 'var(--powder)',
        'lelah' => '#8B7680',
        'sedih' => 'var(--sand)',
    ];
    $hour = (int) now()->format('G');
    $greetKey = $hour < 11
        ? 'Good morning, :name!'
        : ($hour < 15 ? 'Good afternoon, :name!' : ($hour < 18 ? 'Good evening, :name!' : 'Good night, :name!'));
@endphp
<div class="dashboard-page">
    <div class="page-head">
        <div>
            <p class="page-kicker">{{ $diary->range_label }}</p>
            <h1>{{ __($greetKey, ['name' => $user->greeting_name]) }}</h1>
            <p>{{ __('A clear week starts with one intention.') }}</p>
        </div>
        <a href="{{ route('journal.create', ['day' => now()->toDateString()]) }}" class="btn btn-primary">
            <i class="ri-quill-pen-line" aria-hidden="true"></i> {{ __('Write Journal') }}
        </a>
    </div>

    <div class="stat-grid">
        <section class="stat-card progress-stat">
            <div class="progress-ring" style="--progress:{{ $progress }}">
                <svg viewBox="0 0 120 120" aria-hidden="true">
                    <circle class="ring-bg" cx="60" cy="60" r="48"></circle>
                    <circle class="ring-value" cx="60" cy="60" r="48"></circle>
                </svg>
                <div>
                    <strong>{{ $progress }}%</strong>
                    <span>{{ __('weekly') }}</span>
                </div>
            </div>
            <strong class="stat-caption">{{ $completedCount }} {{ __('of') }} {{ $totalTargets }} {{ __('targets') }}</strong>
            <span class="stat-subcaption">{{ __('completed this week') }}</span>
        </section>

        <section class="stat-card journal-stat">
            <span class="stat-icon"><i class="ri-book-open-line" aria-hidden="true"></i></span>
            <div>
                <strong class="big-stat">{{ $journals->count() }}</strong>
                <div class="big-stat-label">{{ __('journals written') }}</div>
                <span class="stat-subcaption">{{ max(0, 7 - $journals->count()) }} {{ __('more days to go') }}</span>
            </div>
        </section>

        <section class="stat-card mood-stat">
            <div class="stat-card-title">
                <strong>{{ __('Mood this week') }}</strong>
                <i class="ri-bar-chart-2-line" aria-hidden="true"></i>
            </div>
            <div class="mood-chart" aria-label="{{ __('Mood this week') }}">
                @foreach($weekDays as $day)
                @php
                    $entry = $day['entry'];
                    $moodKey = $entry ? strtolower($entry->mood ?: 'biasa') : null;
                    $barHeight = $entry ? max(24, min(92, 32 + (($day['date']->dayOfWeekIso * 11) % 58))) : 6;
                    $barColor = $entry ? ($moodColors[$moodKey] ?? 'var(--brand)') : 'var(--line)';
                @endphp
                <div class="mood-column" title="{{ $day['date']->translatedFormat('l') }}{{ $entry ? ' · ' . $entry->mood_label : '' }}">
                    <div class="mood-bar {{ $entry ? 'filled' : '' }}" style="height:{{ $barHeight }}px;background:{{ $barColor }}"></div>
                    <small>{{ $day['date']->isoFormat('dd') }}</small>
                </div>
                @endforeach
            </div>
        </section>
    </div>

    <div class="dashboard-main-grid">
        <section class="dashboard-panel today-journal-panel">
            <div class="panel-heading">
                <div>
                    <span class="panel-kicker">{{ __('Today —') }} {{ now()->translatedFormat('l, d M') }}</span>
                    <h2>{{ __('Today’s Journal') }}</h2>
                </div>
                <i class="ri-quill-pen-line panel-heading-icon" aria-hidden="true"></i>
            </div>
            @if($lastJournal && $lastJournal->entry_date->isToday())
            <div class="today-entry">
                <span class="entry-mood">
                    <i class="{{ $lastJournal->mood_emoji }}" aria-hidden="true"></i>
                    @if($lastJournal->mood)<small style="font-size:.74rem;font-weight:600">{{ $lastJournal->mood_label }}</small>@endif
                </span>
                <strong>{{ $lastJournal->title ?: __('Today’s note') }}</strong>
                <p>{{ \Illuminate\Support\Str::limit($lastJournal->content, 170) }}</p>
            </div>
            <a href="{{ route('journal.edit', $lastJournal->id) }}" class="btn btn-primary btn-block">{{ __('Continue Journal') }}</a>
            @else
            <div class="today-empty">
                <i class="ri-quill-pen-line" aria-hidden="true"></i>
                <p>{{ __('You haven’t written today’s entry yet.') }}</p>
            </div>
            <a href="{{ route('journal.create', ['day' => now()->toDateString()]) }}" class="btn btn-primary btn-block">{{ __('Write Journal') }}</a>
            @endif
        </section>

        <section class="dashboard-panel targets-panel">
            <div class="panel-heading">
                <div>
                    <span class="panel-kicker">{{ __('Weekly focus') }}</span>
                    <h2>{{ __('Today’s Targets') }}</h2>
                </div>
                <a href="{{ route('targets.index') }}" class="panel-link">{{ __('View all') }} →</a>
            </div>
            @if($focusTargets->isEmpty())
            <div class="targets-empty">
                <i class="ri-checkbox-circle-line" aria-hidden="true"></i>
                <p>{{ __('No active targets yet.') }}</p>
            </div>
            @else
            <div class="target-preview-list">
                @foreach($focusTargets as $target)
                <div class="target-preview">
                    <span class="target-check-icon"><i class="ri-check-line" aria-hidden="true"></i></span>
                    <span class="target-preview-title">{{ $target->title }}</span>
                    <span class="badge badge-{{ $target->priority }}">{{ __(ucfirst($target->priority)) }}</span>
                </div>
                @endforeach
            </div>
            @endif
            <a href="{{ route('targets.index') }}" class="btn btn-target-add">
                <i class="ri-add-line" aria-hidden="true"></i> {{ __('Add Target') }}
            </a>
        </section>
    </div>

    <div class="recent-heading">
        <h2>{{ __('Recent Entries') }}</h2>
        <a href="{{ route('journal.index') }}" class="panel-link">{{ __('See all') }} →</a>
    </div>
    <div class="recent-grid">
        @forelse($journals->take(4) as $journal)
        <a href="{{ route('journal.edit', $journal->id) }}" class="recent-card">
            <div class="recent-meta">
                <span>{{ $journal->entry_date->translatedFormat('D, M d') }}</span>
                <i class="{{ $journal->mood ? $journal->mood_emoji : 'ri-quill-pen-line' }}" aria-hidden="true"></i>
            </div>
            <h3>{{ $journal->title ?: __('Untitled entry') }}</h3>
            <p>{{ \Illuminate\Support\Str::limit($journal->content, 100) }}</p>
        </a>
        @empty
        <div class="recent-card recent-empty-card">
            <i class="ri-book-mark-line" aria-hidden="true"></i>
            <p>{{ __('Your recent entries will appear here.') }}</p>
        </div>
        @endforelse
    </div>
</div>
@endsection
