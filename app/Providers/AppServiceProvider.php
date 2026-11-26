<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Batas request AI per menit (batas harian dicek di AiService).
        RateLimiter::for('ai', function (Request $request) {
            return Limit::perMinute((int) config('services.openai.per_minute', 8))
                ->by($request->user()?->id ?: $request->ip());
        });
    }
}
