# Locale

Static words follow the language chosen on the screen. Stored names, codes, and amounts stay as saved.

## Languages

`en` English, `hi` Hindi, `gu` Gujarati.

The choice is kept in the session and in the `locale` cookie. `App\Http\Middleware\SetLocale` applies it on each web request. Route: `locale.update`.

## Words

- Menu, page titles, buttons, form labels, confirmations, and fixed flash messages use `__('English text')`.
- Hindi and Gujarati strings live in `lang/hi.json` and `lang/gu.json`.
- Form errors for the common rules live in `lang/hi/validation.php` and `lang/gu/validation.php`.
- A missing translation shows the English text.
- Employee names, department names, and other saved records are not translated.

The switch sits in the top bar, on the sign-in card, and on More for the phone app. Changing language reloads the current page so every static word updates together.
