<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $user = User::query()->where('email', $request->string('email')->lower()->toString())->first();

        if (! $user || ! $user->is_active || ! $user->hasStaffLogin() || ! Hash::check($request->string('password')->toString(), $user->password)) {
            AuditLogger::record('login_failed', $user, [], ['email' => $request->string('email')->toString()]);

            return back()
                ->withInput($request->only('email'))
                ->with('error', 'These credentials do not match our records.');
        }

        if ($user->requiresMfa()) {
            $request->session()->put('pending_login_id', $user->id);

            return redirect()->route($user->two_factor_confirmed_at ? 'two-factor.challenge' : 'two-factor.setup');
        }

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('mfa_passed', true);
        AuditLogger::record('login_succeeded', $user);

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
