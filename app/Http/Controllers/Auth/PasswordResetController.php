<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;
use Throwable;

class PasswordResetController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = strtolower($data['email']);
        $user = User::query()->where('email', $email)->first();

        if ($user && $user->is_active && $user->hasStaffLogin()) {
            try {
                $status = Password::sendResetLink(['email' => $user->email]);
            } catch (Throwable $exception) {
                report($exception);

                return back()
                    ->withInput($request->only('email'))
                    ->with('error', 'The reset email could not be sent. Ask an administrator to check the mail settings.');
            }

            if ($status === Password::RESET_THROTTLED) {
                return back()
                    ->withInput($request->only('email'))
                    ->with('error', 'Please wait a minute before asking for another link.');
            }
        }

        return back()
            ->withInput($request->only('email'))
            ->with('status', 'If that email can sign in, a reset link is on its way.');
    }

    public function edit(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->string('email')->toString(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
            'password_confirmation' => ['required', 'string'],
        ]);

        $email = strtolower($data['email']);
        $user = User::query()->where('email', $email)->first();

        if (! $user || ! $user->is_active || ! $user->hasStaffLogin()) {
            return back()
                ->withInput($request->only('email'))
                ->with('error', 'This reset link is invalid or has expired.');
        }

        $status = Password::reset(
            [
                'email' => $user->email,
                'password' => $data['password'],
                'password_confirmation' => $data['password_confirmation'],
                'token' => $data['token'],
            ],
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => null,
                ])->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()
                ->withInput($request->only('email'))
                ->with('error', 'This reset link is invalid or has expired.');
        }

        return redirect()
            ->route('login')
            ->with('status', 'Your password is updated. Sign in with the new one.');
    }
}
