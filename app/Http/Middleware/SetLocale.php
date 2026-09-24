<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public const SUPPORTED = ['id', 'en'];

    /**
     * Priority: saved user preference -> session -> cookie -> app default.
     * The user preference survives logout/login; the cookie covers guests.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $candidates = [
            $request->user()?->locale,
            session('locale'),
            $request->cookie('locale'),
            config('app.locale'),
        ];

        foreach ($candidates as $locale) {
            if (is_string($locale) && in_array($locale, self::SUPPORTED, true)) {
                App::setLocale($locale);
                Carbon::setLocale($locale);
                break;
            }
        }

        return $next($request);
    }
}
