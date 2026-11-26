@php
    $size = $size ?? 'md';
    $showSub = $subtitle ?? true;
    $class = trim('logo ' . ($size === 'sm' ? 'logo-sm' : '') . ' ' . ($extraClass ?? ''));
@endphp
<span class="{{ $class }}">
    <img src="{{ asset('images/wedi-logo-mark.png') }}" alt="" class="logo-image">
    <span class="logo-wordmark">
        <strong>WeDi</strong>
        @if($showSub)<small>Weekly Diary</small>@endif
    </span>
</span>
