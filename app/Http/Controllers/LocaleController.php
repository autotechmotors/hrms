<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use Illuminate\Http\RedirectResponse;

class LocaleController extends Controller
{
    public function __invoke(string $locale): RedirectResponse
    {
        abort_unless(in_array($locale, SetLocale::LOCALES, true), 404);

        session(['locale' => $locale]);
        cookie()->queue(cookie()->forever('locale', $locale));

        return redirect()->to(url()->previous() ?: '/');
    }
}
