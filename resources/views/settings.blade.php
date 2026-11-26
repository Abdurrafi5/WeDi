@extends('layouts.app')
@section('title', __('Profile & Settings — WeDi'))
@section('content')
<div class="page-head">
    <div>
        <p class="page-kicker">{{ __('Your space') }}</p>
        <h1>{{ __('Profile & Settings') }}</h1>
        <p>{{ __('Make WeDi feel like yours.') }}</p>
    </div>
</div>

<div class="settings-grid">
    <div class="card">
        <h3>{{ __('Profile') }}</h3>
        <form method="POST" action="{{ route('settings.profile') }}" enctype="multipart/form-data">
            @csrf
            <div class="profile-upload">
                <div class="profile-avatar-wrap">
                    <label class="profile-upload-avatar" for="avatar">
                        @if($user->avatar_url)
                            <img src="{{ $user->avatar_url }}" alt="{{ $user->name }}">
                        @else
                            <span class="profile-avatar-initials">{{ $user->initials }}</span>
                        @endif
                    </label>
                    <label for="avatar" class="profile-avatar-badge" aria-label="{{ __('Change profile picture') }}" title="{{ __('Change profile picture') }}">
                        <i class="ri-camera-line" aria-hidden="true"></i>
                    </label>
                </div>
                <div>
                    <strong>{{ $user->name }}</strong>
                    <span>{{ $user->email }}</span>
                    <label for="avatar" class="upload-link">
                        <i class="ri-image-add-line" aria-hidden="true"></i> {{ __('Change profile picture') }}
                    </label>
                    <input id="avatar" name="avatar" type="file" accept="image/png,image/jpeg,image/webp" class="visually-hidden">
                </div>
            </div>

            <div class="settings-stat-row">
                <div class="s-item"><strong>{{ $journalCount }}</strong><span>{{ __('Journals') }}</span></div>
                <div class="s-item"><strong>{{ $targetCount }}</strong><span>{{ __('Targets') }}</span></div>
                <div class="s-item"><strong>{{ $weekCount }}</strong><span>{{ __('Weeks') }}</span></div>
            </div>

            <div class="field {{ $errors->has('name') ? 'has-error' : '' }}">
                <label for="name">{{ __('Full name') }}</label>
                <input class="input" type="text" id="name" name="name" value="{{ old('name', $user->name) }}" required>
                @error('name')<span class="error-text">{{ $message }}</span>@enderror
            </div>
            <div class="field">
                <label for="nickname">{{ __('Nickname') }}</label>
                <input class="input" type="text" id="nickname" name="nickname" value="{{ old('nickname', $user->nickname) }}" placeholder="{{ __('Example: Ayu') }}">
                <span class="hint">{{ __('Used in your “Good afternoon” greeting.') }}</span>
            </div>
            <button type="submit" class="btn btn-primary">{{ __('Save profile') }}</button>
        </form>
    </div>

    <div class="settings-stack">
        <div class="card">
            <h3>{{ __('Appearance') }}</h3>
            <p>{{ __('Choose a theme that feels comfortable.') }}</p>
            <div class="theme-toggle-row">
                <button type="button" class="theme-opt" data-theme-set="light">
                    <i class="ri-sun-line" aria-hidden="true"></i> {{ __('Light') }}
                </button>
                <button type="button" class="theme-opt" data-theme-set="dark">
                    <i class="ri-moon-line" aria-hidden="true"></i> {{ __('Dark') }}
                </button>
            </div>
            <form method="POST" action="{{ route('settings.appearance') }}" class="switch-row">
                @csrf
                <input type="hidden" name="colorful" value="{{ $user->colorful ? 0 : 1 }}">
                <div>
                    <strong>{{ __('Colorful mode') }}</strong>
                    <p>{{ __('Add soft pink, lilac and peach accents across WeDi.') }}</p>
                </div>
                <button type="submit" class="switch" role="switch" aria-checked="{{ $user->colorful ? 'true' : 'false' }}" aria-label="{{ __('Colorful mode') }}"></button>
            </form>
        </div>

        <div class="card" id="reminders" data-reminder-settings>
            <h3>{{ __('Daily Diary Reminder') }}</h3>
            <p>{{ __('Receive a gentle daily reminder when you have not written today’s entry yet.') }}</p>

            <form method="POST" action="{{ route('reminders.update') }}">
                @csrf
                <input type="hidden" name="enabled" value="{{ $reminderSetting->enabled ? '1' : '0' }}" data-reminder-enabled-input>

                <div class="switch-row reminder-switch-row">
                    <div>
                        <strong>{{ __('Daily reminder') }}</strong>
                        <p>{{ __('Sends at most once per day if today’s diary is still empty.') }}</p>
                    </div>
                    <button type="button" class="switch" role="switch" data-reminder-toggle aria-checked="{{ $reminderSetting->enabled ? 'true' : 'false' }}" aria-label="{{ __('Daily reminder') }}"></button>
                </div>

                <div class="reminder-time-grid">
                    <div class="field {{ $errors->has('reminder_time') ? 'has-error' : '' }}">
                        <label for="reminder_time">{{ __('Reminder time') }}</label>
                        <input class="input" type="time" id="reminder_time" name="reminder_time" value="{{ old('reminder_time', $reminderSetting->reminder_time) }}" required>
                        @error('reminder_time')<span class="error-text">{{ $message }}</span>@enderror
                    </div>

                    <div class="field {{ $errors->has('timezone') ? 'has-error' : '' }}">
                        <label for="reminder_timezone">{{ __('Local timezone') }}</label>
                        @php($tzList = timezone_identifiers_list())
                        <select class="input" id="reminder_timezone" name="timezone" data-reminder-timezone data-auto-tz="{{ $reminderSetting->wasRecentlyCreated ? '1' : '0' }}">
                            @foreach($tzList as $tz)
                            <option value="{{ $tz }}" {{ old('timezone', $reminderSetting->timezone) === $tz ? 'selected' : '' }}>{{ $tz }}</option>
                            @endforeach
                        </select>
                        @error('timezone')<span class="error-text">{{ $message }}</span>@enderror
                    </div>
                </div>

                <div class="reminder-permission-box">
                    <div class="reminder-perm-copy">
                        <strong>{{ __('Browser & device notifications') }}</strong>
                        <p>{{ __('Allow notifications so WeDi can remind you even when this tab is in the background or closed on supported devices.') }}</p>
                        <span class="reminder-perm-status" data-push-status
                              data-default-text="{{ __('Permission not requested yet.') }}"
                              data-granted-text="{{ __('Notifications are active on this device.') }}"
                              data-denied-text="{{ __('Notifications are blocked in your browser settings. You will still see gentle reminders inside WeDi.') }}"
                              data-unsupported-text="{{ __('This browser does not support push notifications. In-app reminders remain active.') }}">
                        </span>
                        <p class="reminder-ios-hint" data-ios-hint hidden>
                            <i class="ri-smartphone-line" aria-hidden="true"></i>
                            {{ __('On iPhone or iPad, tap Share → “Add to Home Screen” first, then open WeDi from your Home Screen to enable device notifications.') }}
                        </p>
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm" data-enable-push
                            data-vapid-key="{{ $vapidPublicKey ?? '' }}"
                            data-active-label="{{ __('Refresh device subscription') }}">
                        <i class="ri-notification-3-line" aria-hidden="true"></i> {{ __('Enable notifications') }}
                    </button>
                </div>

                <div class="reminder-preview" aria-label="{{ __('Notification preview') }}">
                    <span class="panel-kicker">{{ __('Preview') }}</span>
                    <div class="reminder-preview-card">
                        <img src="{{ asset('images/wedi-logo-mark.png') }}" alt="" class="logo-image">
                        <div>
                            <strong data-preview-title>{{ __('A Little Reminder from WeDi') }}</strong>
                            <p>{{ __('Your diary is waiting for you. Take a moment to reflect on your day and write down your thoughts.') }}</p>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">{{ __('Save reminder settings') }}</button>
            </form>
        </div>

        <div class="card">
            <h3>{{ __('Language') }}</h3>
            <div class="lang-toggle-row">
                <form method="POST" action="{{ route('settings.locale') }}">
                    @csrf
                    <input type="hidden" name="locale" value="id">
                    <button type="submit" class="btn {{ app()->getLocale() === 'id' ? 'btn-primary' : 'btn-secondary' }}">Bahasa Indonesia</button>
                </form>
                <form method="POST" action="{{ route('settings.locale') }}">
                    @csrf
                    <input type="hidden" name="locale" value="en">
                    <button type="submit" class="btn {{ app()->getLocale() === 'en' ? 'btn-primary' : 'btn-secondary' }}">English</button>
                </form>
            </div>
        </div>

        <div class="card">
            <h3>{{ __('Change password') }}</h3>
            <form method="POST" action="{{ route('settings.password') }}">
                @csrf
                <div class="field {{ $errors->has('current_password') ? 'has-error' : '' }}">
                    <label for="current_password">{{ __('Current password') }}</label>
                    <input class="input" type="password" id="current_password" name="current_password" required>
                    @error('current_password')<span class="error-text">{{ $message }}</span>@enderror
                </div>
                <div class="field {{ $errors->has('password') ? 'has-error' : '' }}">
                    <label for="new_password">{{ __('New password') }}</label>
                    <input class="input" type="password" id="new_password" name="password" required>
                    @error('password')<span class="error-text">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="new_password_confirmation">{{ __('Confirm new password') }}</label>
                    <input class="input" type="password" id="new_password_confirmation" name="password_confirmation" required>
                </div>
                <button type="submit" class="btn btn-secondary">{{ __('Change password') }}</button>
            </form>
        </div>

        <div class="card account-card">
            <h3>{{ __('Account') }}</h3>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn btn-danger">{{ __('Log out of WeDi') }}</button>
            </form>
        </div>
    </div>
</div>
@endsection
