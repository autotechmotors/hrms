<?php

use App\Http\Middleware\EnsureMfaPassed;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\LogDeviceApi;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->redirectGuestsTo('/login');
        // HTTPS share tunnel (cloudflared / ngrok) — honor X-Forwarded-Proto
        $middleware->trustProxies(at: '*');
        $middleware->validateCsrfTokens(except: [
            'device-events',
            'hikvision-events/*',
        ]);
        $middleware->web(append: [
            SetLocale::class,
        ]);
        $middleware->alias([
            'mfa' => EnsureMfaPassed::class,
            'permission' => EnsurePermission::class,
            'device.log' => LogDeviceApi::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->getRealMethod() !== 'POST' || ! $request->is('employees/*')) {
                return null;
            }

            $message = 'This employee is no longer in the list. Refresh and try again.';
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['message' => $message], 404);
            }

            return redirect()->route('employees.index')->with('error', $message);
        });
    })->create();
