@extends('layouts.guest')
@section('title', __('Daftar — WeDi'))
@section('content')
<div class="auth-wrap">
    <div class="auth-main">
        <div class="auth-card">
            <a href="{{ route('landing') }}" class="logo"><img src="{{ asset("images/wedi-logo-mark.png") }}" alt="WeDi" class="logo-image"><span>WeDi<small>Weekly Diary</small></span></a>
            <p class="auth-sub">{{ __('Mulai simpan cerita mingguanmu, mulai hari ini.') }}</p>

            <form method="POST" action="{{ route('register') }}" novalidate>
                @csrf
                <div class="field {{ $errors->has('name') ? 'has-error' : '' }}">
                    <label for="name">{{ __('Nama') }}</label>
                    <input class="input" type="text" id="name" name="name" value="{{ old('name') }}" required autofocus autocomplete="name">
                    @error('name')<span class="error-text">{{ $message }}</span>@enderror
                </div>
                <div class="field {{ $errors->has('email') ? 'has-error' : '' }}">
                    <label for="email">{{ __('Email') }}</label>
                    <input class="input" type="email" id="email" name="email" value="{{ old('email') }}" required autocomplete="email">
                    @error('email')<span class="error-text">{{ $message }}</span>@enderror
                </div>
                <div class="field {{ $errors->has('password') ? 'has-error' : '' }}">
                    <label for="password">{{ __('Password') }}</label>
                    <input class="input" type="password" id="password" name="password" required autocomplete="new-password">
                    <span class="hint">{{ __('Minimal 8 karakter.') }}</span>
                    @error('password')<span class="error-text">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="password_confirmation">{{ __('Konfirmasi Password') }}</label>
                    <input class="input" type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password">
                </div>
                <button type="submit" class="btn btn-primary btn-block" id="register-submit">{{ __('Daftar') }}</button>
            </form>

            <p class="auth-foot">{{ __('Sudah punya akun?') }} <a href="{{ route('login') }}">{{ __('Masuk di sini') }}</a></p>
            <p class="auth-foot"><a href="{{ route('landing') }}">← {{ __('Kembali ke beranda') }}</a></p>
        </div>
    </div>
</div>
<script>
document.getElementById('register-submit')?.closest('form').addEventListener('submit', function(){
    var b = document.getElementById('register-submit');
    b.disabled = true; b.textContent = '{{ __("Memproses...") }}';
});
</script>
@endsection
