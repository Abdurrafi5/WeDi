<!doctype html>
<html lang="{{ app()->getLocale() }}" data-palette="{{ auth()->user()?->colorful ? 'bloom' : 'classic' }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}"><title>@yield('title','WeDi — Weekly Diary')</title>
    <link rel="icon" href="{{ asset('images/wedi-logo-mark.png') }}"><link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.6.0/remixicon.min.css"><link rel="stylesheet" href="{{ asset('css/wedi.css') }}">
</head>
<body>
@include('partials.flash')
@php($navItems=[['route'=>'dashboard','label'=>__('Home'),'icon'=>'ri-home-5-line','match'=>'dashboard'],['route'=>'journal.index','label'=>__('Diary'),'icon'=>'ri-book-open-line','match'=>'journal.*'],['route'=>'targets.index','label'=>__('Targets'),'icon'=>'ri-checkbox-circle-line','match'=>'targets.*'],['route'=>'recap.index','label'=>__('Recap'),'icon'=>'ri-star-line','match'=>'recap.*'],['route'=>'history.index','label'=>__('History'),'icon'=>'ri-history-line','match'=>'history.*'],['route'=>'games.index','label'=>__('Mini Games'),'icon'=>'ri-game-line','match'=>'games.*']])
@php($user=auth()->user())
<div class="app-shell">
    <aside class="sidebar" aria-label="{{ __('Navigasi utama') }}">
        <a href="{{ route('dashboard') }}" class="logo sidebar-logo"><img src="{{ asset('images/wedi-logo-mark.png') }}" alt="" class="logo-image"><span>WeDi<small>Weekly Diary</small></span></a>
        <ul class="nav-list">@foreach($navItems as $item)<li><a href="{{ route($item['route']) }}" class="nav-link {{ request()->routeIs($item['match'])?'active':'' }}" @if(request()->routeIs($item['match'])) aria-current="page" @endif><i class="{{ $item['icon'] }}" aria-hidden="true"></i><span>{{ $item['label'] }}</span></a></li>@endforeach</ul>
        <div class="sidebar-bottom">
            <a href="{{ route('settings') }}" class="nav-link {{ request()->routeIs('settings')?'active':'' }}"><i class="ri-settings-3-line" aria-hidden="true"></i><span>{{ __('Settings') }}</span></a>
            <div class="sidebar-user">
                <a href="{{ route('settings') }}" class="avatar" aria-label="{{ __('Profile') }}">@if($user->avatar_url)<img src="{{ $user->avatar_url }}" alt="">@else{{ $user->initials }}@endif</a>
                <div class="who"><strong>{{ $user->greeting_name }}</strong><span>{{ $user->email }}</span></div>
                <form method="POST" action="{{ route('logout') }}" class="logout-form">@csrf<button type="submit" class="icon-btn" title="{{ __('Keluar') }}" aria-label="{{ __('Keluar') }}"><i class="ri-logout-box-r-line" aria-hidden="true"></i></button></form>
            </div>
        </div>
    </aside>
    <div class="drawer-overlay" data-drawer-close></div>
    <aside class="mobile-drawer" aria-label="{{ __('Navigasi mobile') }}">
        <div class="drawer-brand"><a href="{{ route('dashboard') }}" class="logo"><img src="{{ asset('images/wedi-logo-mark.png') }}" alt="" class="logo-image"><span>WeDi<small>Weekly Diary</small></span></a><button class="icon-btn" type="button" data-drawer-close aria-label="{{ __('Close menu') }}"><i class="ri-close-line" aria-hidden="true"></i></button></div>
        <ul class="nav-list">@foreach($navItems as $item)<li><a href="{{ route($item['route']) }}" class="nav-link {{ request()->routeIs($item['match'])?'active':'' }}"><i class="{{ $item['icon'] }}" aria-hidden="true"></i><span>{{ $item['label'] }}</span></a></li>@endforeach</ul>
        <div class="sidebar-bottom"><a href="{{ route('settings') }}" class="nav-link"><i class="ri-settings-3-line" aria-hidden="true"></i><span>{{ __('Settings') }}</span></a><form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="nav-link nav-button"><i class="ri-logout-box-r-line" aria-hidden="true"></i><span>{{ __('Keluar') }}</span></button></form></div>
    </aside>
    <div class="app-column">
        <header class="topbar"><button class="icon-btn" type="button" data-drawer-open aria-label="{{ __('Open menu') }}"><i class="ri-menu-line" aria-hidden="true"></i></button><a href="{{ route('dashboard') }}" class="logo"><img src="{{ asset('images/wedi-logo-mark.png') }}" alt="" class="logo-image"><span>WeDi</span></a><a href="{{ route('settings') }}" class="avatar" aria-label="{{ __('Profile') }}">@if($user->avatar_url)<img src="{{ $user->avatar_url }}" alt="">@else{{ $user->initials }}@endif</a></header>
        <main class="main-content"><div class="page">@yield('content')</div></main>
        <nav class="bottom-nav" aria-label="{{ __('Navigasi bawah') }}"><a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard')?'active':'' }}"><i class="ri-home-5-line" aria-hidden="true"></i>{{ __('Home') }}</a><a href="{{ route('journal.index') }}" class="{{ request()->routeIs('journal.*')?'active':'' }}"><i class="ri-book-open-line" aria-hidden="true"></i>{{ __('Diary') }}</a><a href="{{ route('targets.index') }}" class="{{ request()->routeIs('targets.*')?'active':'' }}"><i class="ri-checkbox-circle-line" aria-hidden="true"></i>{{ __('Targets') }}</a><a href="{{ route('games.index') }}" class="{{ request()->routeIs('games.*')?'active':'' }}"><i class="ri-game-line" aria-hidden="true"></i>{{ __('Games') }}</a><a href="{{ route('settings') }}" class="{{ request()->routeIs('settings')?'active':'' }}"><i class="ri-user-line" aria-hidden="true"></i>{{ __('Profile') }}</a></nav>
    </div>
</div>
<script defer src="{{ asset('js/wedi.js') }}"></script>
</body>
</html>
