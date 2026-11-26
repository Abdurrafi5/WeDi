# WeDi — Weekly Diary

Aplikasi diary mingguan berbasis Laravel 13. **Phase 2** menambahkan AI, permainan dengan hand tracking, dan studio gambar.

## Fitur Phase 2

| Fitur | Halaman | Catatan |
|---|---|---|
| **WeDi AI** (chatbox) | `/ai` | Riwayat tersimpan, New Chat, hapus per-percakapan / semua, batas pemakaian |
| **AI Journal Assistant** | `/journal/create`, `/journal/{id}/edit` | Writing Starter, Expand, Improve Clarity, Find the Right Words, Reflection Questions; preview + Copy/Insert |
| **Weekly Reflection AI** | `/recap` | Pilih jurnal (persetujuan wajib), generate 6 bagian, edit, simpan ke Weekly Recap |
| **Hand Tracking Engine** | `public/js/hand-tracking.js` | MediaPipe Hand Landmarker, 21 landmark, pointer telunjuk, pinch, Start/Stop Camera |
| **Bubble Pop** | `/games/bubble-pop` | Canvas, 3 tingkat kesulitan + level naik, kombo, Burst (pinch), skor ke `game_scores` |
| **Hand Gesture Drawing** | `/draw`, `/draw/gallery` | Kuas, warna, penghapus, undo, clear, simpan ke Laravel Storage (privat), galeri |

## Setup Phase 2

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
# isi OPENAI_API_KEY di .env (hanya di server, jangan dibagikan)
php artisan migrate
php artisan serve
```

Variabel `.env` baru: `OPENAI_API_KEY`, `OPENAI_MODEL` (default `gpt-4o-mini`), `OPENAI_BASE_URL`, `OPENAI_TIMEOUT`, `OPENAI_MAX_OUTPUT_TOKENS`, `AI_DAILY_LIMIT` (default 40/hari/pengguna), `AI_PER_MINUTE_LIMIT` (default 8/menit).

Hand tracking memerlukan **HTTPS atau `localhost`** (syarat browser untuk akses kamera) dan internet untuk memuat MediaPipe + model dari CDN saat kamera pertama kali diaktifkan.

## Keamanan & privasi

- API key hanya dibaca server dari `.env`; browser hanya memanggil endpoint WeDi.
- Semua percakapan, refleksi, dan artwork di-scope ke pemiliknya (`findOrFail` lewat relasi user → 404 untuk orang lain).
- Jurnal hanya dikirim ke AI setelah pengguna mencentang jurnalnya **dan** menyetujui pengiriman.
- Batas pemakaian: throttle per menit (`throttle:ai`) + batas harian di `AiService`; setiap panggilan dicatat di `ai_usage_logs`.
- Webcam hanya aktif setelah tombol ditekan + izin browser; video diproses lokal, tidak dikirim/disimpan.
- Artwork disimpan di disk privat (`storage/app/private/artworks/{user_id}`) dan hanya disajikan lewat route yang memeriksa pemilik; hanya tersimpan saat pengguna menekan Simpan.

## Pengujian

```bash
php artisan test
```

Test baru: `AiChatTest`, `AiJournalAssistantTest`, `ArtworkTest` (OpenAI di-fake dengan `Http::fake`).

---

<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
