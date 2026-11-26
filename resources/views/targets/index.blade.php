@extends('layouts.app')
@section('title', __('Targets — WeDi'))
@section('content')
<div class="dashboard-page targets-page">
    <div class="page-head">
        <div>
            <p class="page-kicker">{{ __('Weekly focus') }}</p>
            <h1>{{ __('Targets') }}</h1>
            <p>{{ $diary->range_label }}</p>
        </div>
        <button type="button" class="btn btn-primary" data-modal-open="add-target-modal">
            <i class="ri-add-line" aria-hidden="true"></i> {{ __('Add Target') }}
        </button>
    </div>

    <section class="target-summary-card">
        <div class="target-summary-copy">
            <span class="panel-kicker">{{ __('Your progress') }}</span>
            <h2>{{ $completedCount }} {{ __('of') }} {{ $totalTargets }} {{ __('targets completed') }}</h2>
            <p>{{ $remainingCount ? __('Keep going with the next small step.') : __('Everything is complete. Take a moment to celebrate.') }}</p>
        </div>
        <div class="target-summary-ring" style="--progress:{{ $progress }}">
            <svg viewBox="0 0 120 120" aria-hidden="true">
                <circle class="ring-bg" cx="60" cy="60" r="48"></circle>
                <circle class="ring-value" cx="60" cy="60" r="48"></circle>
            </svg>
            <strong>{{ $progress }}%</strong>
        </div>
        <div class="target-summary-numbers">
            <div><strong>{{ $totalTargets }}</strong><span>{{ __('total') }}</span></div>
            <div><strong>{{ $remainingCount }}</strong><span>{{ __('to go') }}</span></div>
        </div>
    </section>

    <div class="target-toolbar">
        <div>
            <h2>{{ __('This week’s targets') }}</h2>
            <p>{{ __('Added during this week') }}</p>
        </div>
        <span class="target-count"><i class="ri-checkbox-circle-line" aria-hidden="true"></i> {{ $completedCount }}/{{ $totalTargets }}</span>
    </div>

    @if($targets->isEmpty())
    <div class="card">
        <div class="empty-state">
            <i class="ri-checkbox-circle-line empty-icon" aria-hidden="true"></i>
            <h3>{{ __('A clear week starts with one intention.') }}</h3>
            <p>{{ __('Add a small target to give your week a gentle direction.') }}</p>
            <button type="button" class="btn btn-primary" data-modal-open="add-target-modal">{{ __('Add your first target') }}</button>
        </div>
    </div>
    @else
    <div class="targets-board">
        @foreach($targets as $target)
        <article class="target-row {{ $target->is_completed ? 'is-done' : '' }}">
            <form method="POST" action="{{ route('targets.toggle', $target->id) }}" class="target-toggle-form">
                @csrf
                @method('PATCH')
                <button type="submit" class="target-check-button" aria-label="{{ $target->is_completed ? __('Mark as incomplete') : __('Mark as complete') }}">
                    <i class="{{ $target->is_completed ? 'ri-check-line' : 'ri-checkbox-blank-circle-line' }}" aria-hidden="true"></i>
                </button>
            </form>
            <div class="target-row-content">
                <div class="target-row-title">
                    <h3>{{ $target->title }}</h3>
                    <span class="badge badge-{{ $target->priority }}">{{ __(ucfirst($target->priority)) }}</span>
                </div>
                @if($target->description)<p>{{ $target->description }}</p>@endif
                <span class="target-row-meta">
                    <i class="ri-calendar-line" aria-hidden="true"></i>
                    {{ __('Added') }} {{ $target->created_at->translatedFormat('l, d M') }}
                    @if($target->completed_at) · {{ __('Completed') }} {{ $target->completed_at->translatedFormat('d M') }} @endif
                </span>
            </div>
            <form method="POST" action="{{ route('targets.destroy', $target->id) }}" onsubmit="return confirm('{{ __('Delete this target?') }}');">
                @csrf
                @method('DELETE')
                <button type="submit" class="icon-button target-delete" aria-label="{{ __('Delete target') }}">
                    <i class="ri-delete-bin-line" aria-hidden="true"></i>
                </button>
            </form>
        </article>
        @endforeach
    </div>
    @endif
</div>

<div class="modal-overlay" id="add-target-modal">
    <div class="modal-box">
        <div class="modal-head">
            <div>
                <span class="panel-kicker">{{ __('New focus') }}</span>
                <h3>{{ __('Add Target') }}</h3>
            </div>
            <button type="button" class="modal-close" data-modal-close aria-label="{{ __('Close') }}">
                <i class="ri-close-line" aria-hidden="true"></i>
            </button>
        </div>
        <form method="POST" action="{{ route('targets.store') }}">
            @csrf
            <div class="field {{ $errors->has('title') ? 'has-error' : '' }}">
                <label for="target-title">{{ __('Target title') }}</label>
                <input class="input" type="text" id="target-title" name="title" value="{{ old('title') }}" placeholder="{{ __('Example: Read 30 pages') }}" required>
                @error('title')<span class="error-text">{{ $message }}</span>@enderror
            </div>
            <div class="field">
                <label for="target-desc">{{ __('Description') }} <span class="muted">({{ __('optional') }})</span></label>
                <textarea class="textarea" id="target-desc" name="description" style="min-height:90px" placeholder="{{ __('What would make this feel complete?') }}">{{ old('description') }}</textarea>
            </div>
            <div class="field">
                <label for="target-priority">{{ __('Priority') }}</label>
                <select class="input" id="target-priority" name="priority">
                    <option value="low" {{ old('priority') === 'low' ? 'selected' : '' }}>{{ __('Low') }}</option>
                    <option value="medium" {{ old('priority', 'medium') === 'medium' ? 'selected' : '' }}>{{ __('Medium') }}</option>
                    <option value="high" {{ old('priority') === 'high' ? 'selected' : '' }}>{{ __('High') }}</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary btn-block"><i class="ri-add-line" aria-hidden="true"></i> {{ __('Save target') }}</button>
        </form>
    </div>
</div>
@if($errors->any() && old('title') !== null)<script>document.getElementById('add-target-modal').classList.add('open');</script>@endif
@endsection
