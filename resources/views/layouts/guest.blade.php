<!doctype html>
<html lang="{{ app()->getLocale() }}" translate="no">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="google" content="notranslate">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'WeDi — Weekly Diary')</title>
    <link rel="icon" href="{{ asset('images/wedi-logo-mark.png') }}">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <meta name="theme-color" content="#3A498F">
    <link rel="apple-touch-icon" href="{{ asset('images/wedi-logo-mark.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Fraunces:ital,opsz,wght@0,9..144,500;0,9..144,600;0,9..144,700;1,9..144,500&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.6.0/remixicon.min.css">
    <link rel="stylesheet" href="{{ asset('css/wedi.css') }}?v={{ filemtime(public_path('css/wedi.css')) }}">
</head>
<body>
@include('partials.flash')
@yield('content')
<script defer src="{{ asset('js/wedi.js') }}?v={{ filemtime(public_path('js/wedi.js')) }}"></script>
</body>
</html>
