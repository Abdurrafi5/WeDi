@extends('layouts.guest')
@section('title', 'WeDi — Your Week, Your Story.')
@section('content')
<header class="guest-header">
    <div class="container">
        <a href="{{ route('landing') }}" class="logo-link" aria-label="WeDi — Weekly Diary">
            @include('partials.logo')
        </a>
        <nav class="guest-nav" aria-label="{{ __('Navigasi utama') }}">
            <form method="POST" action="{{ route('settings.locale') }}" class="lang-switch">
                @csrf
                <input type="hidden" name="locale" value="{{ app()->getLocale() === 'id' ? 'en' : 'id' }}">
                <button type="submit" class="btn btn-ghost btn-sm" aria-label="{{ __('Bahasa') }}">
                    <i class="ri-translate-2" aria-hidden="true"></i> {{ app()->getLocale() === 'id' ? 'EN' : 'ID' }}
                </button>
            </form>
            <a href="{{ route('login') }}" class="btn btn-ghost">{{ __('Masuk') }}</a>
            <a href="{{ route('register') }}" class="btn btn-primary">{{ __('Buka ruangmu') }}</a>
        </nav>
    </div>
</header>

<section class="hero">
    <div class="container hero-grid">
        <div class="reveal-stagger">
            <p class="hero-eyebrow">{{ __('Your Week, Your Story.') }}</p>
            <h1>{{ __('Cerita kecilmu') }}<br><em>{{ __('layak disimpan.') }}</em></h1>
            <p class="hero-lead">{{ __('WeDi adalah diary mingguan yang hangat untuk menulis jurnal, menjaga target sederhana, dan melihat kembali minggu yang sudah kamu lewati.') }}</p>
            <div class="hero-cta">
                <a href="{{ route('register') }}" class="btn btn-primary">
                    <span>{{ __('Mulai menulis') }}</span>
                    <i class="ri-arrow-right-line btn-arrow" aria-hidden="true"></i>
                </a>
                <a href="#cara-kerja" class="btn btn-secondary">
                    <span>{{ __('Lihat cara kerja') }}</span>
                    <i class="ri-arrow-down-line" aria-hidden="true"></i>
                </a>
            </div>
            <div class="hero-proof">
                <span>{{ __('Senin—Minggu') }}</span>
                <span>{{ __('Pribadi') }}</span>
                <span>{{ __('Pelan-pelan') }}</span>
            </div>
        </div>

        <div class="hero-desk" aria-hidden="true">
            <div class="hero-desk-glow"></div>
            <div class="diary-sheet">
                <div class="diary-sheet-top">
                    <div class="diary-brand">
                        @include('partials.logo', ['size' => 'sm', 'subtitle' => false])
                        <span class="diary-issue">/ 01</span>
                    </div>
                    <div class="diary-week-dots">
                        <span class="is-filled">M</span>
                        <span class="is-filled">T</span>
                        <span class="is-active">W</span>
                        <span>T</span>
                        <span>F</span>
                        <span>S</span>
                        <span>S</span>
                    </div>
                </div>
                <div class="diary-entry-preview">
                    <span class="diary-mood-tag"><i class="ri-emotion-happy-line"></i> {{ __('Senang') }}</span>
                    <div class="diary-title">{{ __('Your week,') }}<br><span>{{ __('your story.') }}</span></div>
                    <p class="diary-copy">{{ __('Tulis satu hal yang ingin kamu ingat dari hari ini.') }}</p>
                    <div class="diary-lines"><i></i><i></i><i></i></div>
                </div>
                <div class="diary-sheet-foot">
                    <span>{{ __('Weekly diary') }}</span>
                    <span class="diary-pill"><i class="ri-quill-pen-line"></i> {{ __('small wins count') }}</span>
                </div>
            </div>

            <div class="diary-float-targets">
                <div class="dft-head">
                    <span>{{ __('Targets') }}</span>
                    <strong>2/3</strong>
                </div>
                <div class="dft-bar"><span></span></div>
                <div class="dft-item is-done"><i class="ri-checkbox-circle-fill"></i><span>{{ __('A slower morning') }}</span></div>
                <div class="dft-item"><i class="ri-checkbox-blank-circle-line"></i><span>{{ __('Your weekly recap') }}</span></div>
            </div>
        </div>
    </div>
</section>

<section id="cara-kerja" class="section section-alt">
    <div class="container">
        <div class="section-head" data-reveal>
            <p class="eyebrow">{{ __('Tidak perlu rumit') }}</p>
            <h2>{{ __('Satu minggu yang terasa lebih utuh, satu catatan setiap hari.') }}</h2>
        </div>
        <div class="how-grid">
            <article class="how-card" data-reveal style="--reveal-delay: 0.04s">
                <div class="num">01</div>
                <h3>{{ __('Pilih harinya') }}</h3>
                <p>{{ __('Senin sampai Minggu sudah tersusun. Kamu tidak perlu mengetik tanggal lagi.') }}</p>
            </article>
            <article class="how-card" data-reveal style="--reveal-delay: 0.12s">
                <div class="num">02</div>
                <h3>{{ __('Tulis seadanya') }}</h3>
                <p>{{ __('Simpan cerita, mood, dan momen kecil tanpa harus membuatnya sempurna.') }}</p>
            </article>
            <article class="how-card" data-reveal style="--reveal-delay: 0.2s">
                <div class="num">03</div>
                <h3>{{ __('Lihat kembali') }}</h3>
                <p>{{ __('Tutup minggu dengan refleksi yang jujur dan terasa dekat dengan dirimu.') }}</p>
            </article>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-head" data-reveal>
            <p class="eyebrow">{{ __('Ruang personal') }}</p>
            <h2>{{ __('Menulis adalah fokusnya. Yang lain membantu menjaganya.') }}</h2>
        </div>
        <div class="feature-grid">
            <article class="feature-card" data-reveal style="--reveal-delay: 0.04s">
                <span class="icon"><i class="ri-book-open-line" aria-hidden="true"></i></span>
                <h3>{{ __('Jurnal harian') }}</h3>
                <p>{{ __('Satu halaman untuk setiap hari dalam minggu aktif.') }}</p>
            </article>
            <article class="feature-card" data-reveal style="--reveal-delay: 0.1s">
                <span class="icon"><i class="ri-checkbox-circle-line" aria-hidden="true"></i></span>
                <h3>{{ __('Target kecil') }}</h3>
                <p>{{ __('Arah sederhana agar minggu tidak terasa berjalan begitu saja.') }}</p>
            </article>
            <article class="feature-card" data-reveal style="--reveal-delay: 0.16s">
                <span class="icon"><i class="ri-star-line" aria-hidden="true"></i></span>
                <h3>{{ __('Rekap mingguan') }}</h3>
                <p>{{ __('Ruang untuk menghargai apa yang sudah kamu jalani.') }}</p>
            </article>
            <article class="feature-card" data-reveal style="--reveal-delay: 0.22s">
                <span class="icon"><i class="ri-game-line" aria-hidden="true"></i></span>
                <h3>{{ __('Jeda singkat') }}</h3>
                <p>{{ __('Mini game sederhana ketika kepala perlu istirahat.') }}</p>
            </article>
        </div>

        <div class="closing-cta" data-reveal>
            <div>
                <p class="eyebrow">{{ __('Your space') }}</p>
                <h2>{{ __('Mulai simpan cerita mingguanmu, mulai hari ini.') }}</h2>
            </div>
            <div class="closing-cta-actions">
                <a href="{{ route('register') }}" class="btn btn-primary">{{ __('Buka ruangmu') }} <i class="ri-arrow-right-line btn-arrow" aria-hidden="true"></i></a>
                <a href="{{ route('login') }}" class="btn btn-secondary">{{ __('Masuk') }}</a>
            </div>
        </div>
    </div>
</section>

<footer class="footer">
    <div class="container">
        <span>WeDi — Weekly Diary · {{ date('Y') }}</span>
        <span>{{ __('Your Week, Your Story.') }}</span>
    </div>
</footer>
@endsection
