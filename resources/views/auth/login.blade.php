@extends('layouts.guest')
@section('title', __('Masuk — WeDi'))
@section('content')
<div class="auth-wrap">
    <div class="auth-main">
        <div class="auth-copy reveal-stagger">
            <p class="eyebrow">{{ __('Your Week, Your Story.') }}</p>
            <h1>{{ __('Cerita kecilmu') }} <em>{{ __('layak disimpan.') }}</em></h1>
            <p>{{ __('Selamat datang kembali. Ceritamu menunggu untuk dilanjutkan.') }}</p>
            <blockquote class="auth-quote">
                “{{ __('Tulis satu hal yang ingin kamu ingat dari hari ini.') }}”
            </blockquote>
        </div>

        <div class="auth-card">
            <a href="{{ route('landing') }}" class="logo-link auth-logo" aria-label="WeDi — Weekly Diary">
                @include('partials.logo')
            </a>
            <p class="auth-sub">{{ __('Selamat datang kembali. Ceritamu menunggu untuk dilanjutkan.') }}</p>

            <form method="POST" action="{{ route('login') }}" novalidate>
                @csrf
                <div class="field {{ $errors->has('email') ? 'has-error' : '' }}">
                    <label for="email">{{ __('Email') }}</label>
                    <input class="input" type="email" id="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email">
                    @error('email')<span class="error-text">{{ $message }}</span>@enderror
                </div>
                <div class="field {{ $errors->has('password') ? 'has-error' : '' }}">
                    <label for="password">{{ __('Password') }}</label>
                    <input class="input" type="password" id="password" name="password" required autocomplete="current-password">
                    @error('password')<span class="error-text">{{ $message }}</span>@enderror
                </div>
                <div class="field" style="display:flex;align-items:center;justify-content:space-between;">
                    <label class="checkbox-row">
                        <input type="checkbox" name="remember">
                        <span class="checkbox-box" aria-hidden="true"></span>
                        <span>{{ __('Ingat saya') }}</span>
                    </label>
                </div>
                <button type="submit" class="btn btn-primary btn-block" id="login-submit">{{ __('Masuk') }}</button>
            </form>

            <p class="auth-foot">{{ __('Belum punya akun?') }} <a href="{{ route('register') }}">{{ __('Daftar di sini') }}</a></p>
            <p class="auth-foot"><a href="{{ route('landing') }}"><i class="ri-arrow-left-line" aria-hidden="true"></i> {{ __('Kembali ke beranda') }}</a></p>
        </div>
    </div>
</div>
<script>
document.getElementById('login-submit')?.closest('form').addEventListener('submit', function(){
    var b = document.getElementById('login-submit');
    b.disabled = true; b.textContent = '{{ __("Memproses...") }}';
});
</script>
@endsection
