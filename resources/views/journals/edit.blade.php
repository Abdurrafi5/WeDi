@extends('layouts.app')
@section('title', __('Edit Journal — WeDi'))
@section('content')
<div class="dashboard-page">
    <div class="page-head">
        <div>
            <p class="page-kicker">{{ __('Saved entry') }}</p>
            <h1>{{ __('Edit journal') }}</h1>
            <p>{{ $selectedDate->translatedFormat('l, d F Y') }}</p>
        </div>
        <a href="{{ route('journal.index') }}" class="btn btn-secondary">
            <i class="ri-arrow-left-line" aria-hidden="true"></i> {{ __('Back to week') }}
        </a>
    </div>

    <div class="card journal-editor-card">
        <form method="POST" action="{{ route('journal.update', $journal->id) }}">
            @csrf
            @method('PUT')
            <div class="selected-day">
                <span>{{ __('Writing day') }}</span>
                <strong>{{ $selectedDate->translatedFormat('l, d F Y') }}</strong>
                <a href="{{ route('journal.index') }}">{{ __('Back to week') }}</a>
            </div>

            <div class="field {{ $errors->has('title') ? 'has-error' : '' }}">
                <label for="title">{{ __('Title') }}</label>
                <input class="input" type="text" id="title" name="title" maxlength="160" value="{{ old('title', $journal->title) }}">
                @error('title')<span class="error-text">{{ $message }}</span>@enderror
            </div>

            <div class="field {{ $errors->has('mood') ? 'has-error' : '' }}">
                <label>{{ __('Mood') }}</label>
                <div class="mood-picker">
                    @foreach($moods as $key => $icon)
                    <label class="mood-opt">
                        <input type="radio" name="mood" value="{{ $key }}" {{ old('mood', $journal->mood) === $key ? 'checked' : '' }}>
                        <span class="mo-face"><i class="{{ $icon }}" aria-hidden="true"></i><span>{{ ucfirst(__($key)) }}</span></span>
                    </label>
                    @endforeach
                </div>
                @error('mood')<span class="error-text">{{ $message }}</span>@enderror
            </div>

            <div class="field {{ $errors->has('content') ? 'has-error' : '' }}">
                <label for="content">{{ __('Today’s story') }}</label>
                <textarea class="textarea journal-textarea" id="content" name="content" required>{{ old('content', $journal->content) }}</textarea>
                @error('content')<span class="error-text">{{ $message }}</span>@enderror
            </div>

            @include('partials.ai-journal-assistant')

            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><i class="ri-save-line" aria-hidden="true"></i> {{ __('Save changes') }}</button>
                <a href="{{ route('journal.index') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
            </div>
        </form>
    </div>
</div>
@endsection
