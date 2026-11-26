<!doctype html>
<html lang="{{ app()->getLocale() }}" translate="no" data-palette="{{ auth()->user()?->colorful ? 'bloom' : 'classic' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="google" content="notranslate">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'WeDi — Weekly Diary')</title>
    <link rel="icon" href="{{ asset('images/wedi-logo-mark.png') }}">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <meta name="theme-color" content="#3A498F">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <link rel="apple-touch-icon" href="{{ asset('images/wedi-logo-mark.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Fraunces:ital,opsz,wght@0,9..144,500;0,9..144,600;0,9..144,700;1,9..144,500&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.6.0/remixicon.min.css">
    <link rel="stylesheet" href="{{ asset('css/wedi.css') }}?v={{ filemtime(public_path('css/wedi.css')) }}">
</head>
<body>
@include('partials.flash')
@php
    $navItems = [
        ['route' => 'dashboard', 'label' => __('Home'), 'icon' => 'ri-home-5-line', 'match' => 'dashboard'],
        ['route' => 'journal.index', 'label' => __('Diary'), 'icon' => 'ri-book-open-line', 'match' => 'journal.*'],
        ['route' => 'targets.index', 'label' => __('Targets'), 'icon' => 'ri-checkbox-circle-line', 'match' => 'targets.*'],
        ['route' => 'recap.index', 'label' => __('Recap'), 'icon' => 'ri-star-line', 'match' => 'recap.*'],
        ['route' => 'history.index', 'label' => __('History'), 'icon' => 'ri-history-line', 'match' => 'history.*'],
        ['route' => 'games.index', 'label' => __('Mini Games'), 'icon' => 'ri-game-line', 'match' => ['games.*', 'draw.*', 'filter.*']],
        ['route' => 'ai.index', 'label' => 'WeDi AI', 'icon' => 'ri-sparkling-2-line', 'match' => 'ai.*'],
    ];
    $user = auth()->user();
@endphp
<div class="app-shell" data-reminder-root data-reminder-status-url="{{ route('reminders.status') }}" data-push-subscribe-url="{{ route('reminders.push.subscribe') }}">
    <aside class="sidebar" aria-label="{{ __('Navigasi utama') }}">
        <a href="{{ route('dashboard') }}" class="logo-link sidebar-logo" aria-label="WeDi — Weekly Diary">
            @include('partials.logo')
        </a>
        <ul class="nav-list">
            @foreach($navItems as $item)
            @php($isActive = request()->routeIs(...(array) $item['match']))
            <li>
                <a href="{{ route($item['route']) }}" class="nav-link {{ $isActive ? 'active' : '' }}" @if($isActive) aria-current="page" @endif>
                    <i class="{{ $item['icon'] }}" aria-hidden="true"></i>
                    <span>{{ $item['label'] }}</span>
                </a>
            </li>
            @endforeach
        </ul>
        <div class="sidebar-bottom">
            <button type="button" class="nav-link reminder-nav-btn" data-reminder-bell aria-expanded="false" aria-controls="reminder-popover">
                <i class="ri-notification-3-line" aria-hidden="true"></i>
                <span>{{ __('Reminders') }}</span>
                <span class="reminder-dot" data-reminder-dot hidden aria-hidden="true"></span>
            </button>
            <a href="{{ route('settings') }}" class="nav-link {{ request()->routeIs('settings') ? 'active' : '' }}" @if(request()->routeIs('settings')) aria-current="page" @endif>
                <i class="ri-settings-3-line" aria-hidden="true"></i>
                <span>{{ __('Settings') }}</span>
            </a>
            <div class="sidebar-user">
                <a href="{{ route('settings') }}" class="avatar" aria-label="{{ __('Profile') }}">
                    @if($user->avatar_url)<img src="{{ $user->avatar_url }}" alt="">@else{{ $user->initials }}@endif
                </a>
                <div class="who">
                    <strong>{{ $user->greeting_name }}</strong>
                    <span>{{ $user->email }}</span>
                </div>
                <form method="POST" action="{{ route('logout') }}" class="logout-form">
                    @csrf
                    <button type="submit" class="icon-btn" title="{{ __('Keluar') }}" aria-label="{{ __('Keluar') }}">
                        <i class="ri-logout-box-r-line" aria-hidden="true"></i>
                    </button>
                </form>
            </div>
        </div>
    </aside>

    <div class="drawer-overlay" data-drawer-close></div>
    <aside class="mobile-drawer" aria-label="{{ __('Navigasi mobile') }}">
        <div class="drawer-brand">
            <a href="{{ route('dashboard') }}" class="logo-link" aria-label="WeDi — Weekly Diary">
                @include('partials.logo')
            </a>
            <button class="icon-btn" type="button" data-drawer-close aria-label="{{ __('Close menu') }}">
                <i class="ri-close-line" aria-hidden="true"></i>
            </button>
        </div>
        <ul class="nav-list">
            @foreach($navItems as $item)
            @php($isActive = request()->routeIs(...(array) $item['match']))
            <li>
                <a href="{{ route($item['route']) }}" class="nav-link {{ $isActive ? 'active' : '' }}" @if($isActive) aria-current="page" @endif>
                    <i class="{{ $item['icon'] }}" aria-hidden="true"></i>
                    <span>{{ $item['label'] }}</span>
                </a>
            </li>
            @endforeach
        </ul>
        <div class="sidebar-bottom">
            <a href="{{ route('settings') }}" class="nav-link {{ request()->routeIs('settings') ? 'active' : '' }}">
                <i class="ri-settings-3-line" aria-hidden="true"></i>
                <span>{{ __('Settings') }}</span>
            </a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="nav-link">
                    <i class="ri-logout-box-r-line" aria-hidden="true"></i>
                    <span>{{ __('Keluar') }}</span>
                </button>
            </form>
        </div>
    </aside>

    <div class="app-column">
        <header class="topbar">
            <button class="icon-btn" type="button" data-drawer-open aria-label="{{ __('Open menu') }}">
                <i class="ri-menu-line" aria-hidden="true"></i>
            </button>
            <a href="{{ route('dashboard') }}" class="logo-link" aria-label="WeDi">
                @include('partials.logo', ['size' => 'sm', 'subtitle' => false])
            </a>
            <div class="topbar-actions">
                <button type="button" class="icon-btn reminder-topbar-btn" data-reminder-bell aria-expanded="false" aria-controls="reminder-popover" aria-label="{{ __('Reminders') }}">
                    <i class="ri-notification-3-line" aria-hidden="true"></i>
                    <span class="reminder-dot" data-reminder-dot hidden aria-hidden="true"></span>
                </button>
                <a href="{{ route('settings') }}" class="avatar" aria-label="{{ __('Profile') }}">
                    @if($user->avatar_url)<img src="{{ $user->avatar_url }}" alt="">@else{{ $user->initials }}@endif
                </a>
            </div>
        </header>

        <div class="reminder-popover" id="reminder-popover" data-reminder-popover hidden role="dialog" aria-label="{{ __('Daily reminder') }}">
            <div class="reminder-popover-head">
                <strong>{{ __('Daily reminder') }}</strong>
                <div class="reminder-popover-links">
                    <a href="{{ route('settings') }}#reminders">{{ __('Settings') }}</a>
                    <button type="button" class="icon-btn" data-reminder-close aria-label="{{ __('Close') }}">
                        <i class="ri-close-line" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
            <div class="reminder-popover-body" data-reminder-list
                 data-empty-text="{{ __('No reminders right now. Your daily pages are always open.') }}"
                 data-done-text="{{ __('You already wrote your diary today. Well done.') }}"
                 data-cta-text="{{ __('Write today’s diary') }}"
                 data-read-text="{{ __('Dismiss') }}">
            </div>
        </div>

        <main class="main-content">
            <div class="page">@yield('content')</div>
        </main>

        <nav class="bottom-nav" aria-label="{{ __('Navigasi bawah') }}">
            <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <i class="ri-home-5-line" aria-hidden="true"></i>{{ __('Home') }}
            </a>
            <a href="{{ route('journal.index') }}" class="{{ request()->routeIs('journal.*') ? 'active' : '' }}">
                <i class="ri-book-open-line" aria-hidden="true"></i>{{ __('Diary') }}
            </a>
            <a href="{{ route('targets.index') }}" class="{{ request()->routeIs('targets.*') ? 'active' : '' }}">
                <i class="ri-checkbox-circle-line" aria-hidden="true"></i>{{ __('Targets') }}
            </a>
            <a href="{{ route('games.index') }}" class="{{ request()->routeIs('games.*', 'draw.*', 'filter.*') ? 'active' : '' }}">
                <i class="ri-game-line" aria-hidden="true"></i>{{ __('Games') }}
            </a>
            <a href="{{ route('ai.index') }}" class="{{ request()->routeIs('ai.*') ? 'active' : '' }}">
                <i class="ri-sparkling-2-line" aria-hidden="true"></i>AI
            </a>
        </nav>
    </div>
</div>
<script defer src="{{ asset('js/wedi.js') }}?v={{ filemtime(public_path('js/wedi.js')) }}"></script>
<script defer src="{{ asset('js/reminders.js') }}?v={{ filemtime(public_path('js/reminders.js')) }}"></script>
@stack('scripts')
</body>
</html>
