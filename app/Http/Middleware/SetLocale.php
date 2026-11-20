<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public const LOCALES = ['en', 'hi', 'gu'];

    public function handle(Request $request, Closure $next): Response
    {
        self::apply($request);

        return $next($request);
    }

    public static function apply(Request $request): void
    {
        $locale = (string) config('app.locale', 'en');
        $cookie = (string) $request->cookie('locale', '');
        if (in_array($cookie, self::LOCALES, true)) {
            $locale = $cookie;
        }
        if ($request->hasSession()) {
            $chosen = (string) $request->session()->get('locale', '');
            if (in_array($chosen, self::LOCALES, true)) {
                $locale = $chosen;
            }
        }
        if (! in_array($locale, self::LOCALES, true)) {
            $locale = 'en';
        }

        app()->setLocale($locale);
        Carbon::setLocale($locale);
    }
}
