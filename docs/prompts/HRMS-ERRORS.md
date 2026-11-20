# Error pages

Every HTML error uses one card on the same stage as sign-in. The code, title, and sentence come from `App\Support\HttpErrorCopy`. English is the source. Hindi and Gujarati are in `lang/hi.json` and `lang/gu.json`.

An unknown address falls through `Route::fallback` so the web middleware, including the language choice, still runs. Laravel then looks up `resources/views/errors/{status}.blade.php`, then `{4xx|5xx}.blade.php`. Those views extend `errors/minimal.blade.php`. A missing page, a forbidden page, an expired form (419), a throttle (429), a server error, and maintenance (503) all use that card. Any other 4xx or 5xx code uses the same card with its own sentence when the code is listed, or a short fallback sentence when it is not.

JSON and Ajax responses stay JSON. The employee list still redirects a missing POST back to the list. Login, logout, and MFA stay normal HTTP. The page does not read the signed-in user, so a database failure can still render it.

Go home returns to `/`. Back uses the browser history.
