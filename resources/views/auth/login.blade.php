@extends('layouts.guest')
@section('title', __('Masuk — WeDi'))
@section('content')
<div class="auth-wrap">
    <div class="auth-main">
        <div class="auth-card">
            <a href="{{ route('landing') }}" class="logo"><img src="{{ asset("images/wedi-logo-mark.png") }}" alt="WeDi" class="logo-image"><span>WeDi<small>Weekly Diary</small></span></a>
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
                    <label class="checkbox-row"><input type="checkbox" name="remember"> {{ __('Ingat saya') }}</label>
                </div>
                <button type="submit" class="btn btn-primary btn-block" id="login-submit">{{ __('Masuk') }}</button>
            </form>

            <p class="auth-foot">{{ __('Belum punya akun?') }} <a href="{{ route('register') }}">{{ __('Daftar di sini') }}</a></p>
            <p class="auth-foot"><a href="{{ route('landing') }}">← {{ __('Kembali ke beranda') }}</a></p>
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
