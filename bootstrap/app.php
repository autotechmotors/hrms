<?php

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
        $middleware->validateCsrfTokens(except: [
            'device-events',
            'ezapi/*',
        ]);
        $middleware->alias([
            'mfa' => \App\Http\Middleware\EnsureMfaPassed::class,
            'permission' => \App\Http\Middleware\EnsurePermission::class,
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
