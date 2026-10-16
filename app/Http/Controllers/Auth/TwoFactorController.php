<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TwoFactorController extends Controller
{
    public function __construct(private readonly TwoFactorService $twoFactor)
    {
    }

    public function setup(Request $request): View|RedirectResponse
    {
        $user = $this->pendingUser($request);
        if ($user->two_factor_confirmed_at) {
            return redirect()->route('two-factor.challenge');
        }

        $this->twoFactor->ensureSecret($user);

        return view('auth.two-factor-setup', [
            'qr' => $this->twoFactor->qrSvg($user->refresh()),
            'secret' => $user->two_factor_secret,
        ]);
    }

    public function confirm(Request $request): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:20']]);
        $user = $this->pendingUser($request);
        $codes = $this->twoFactor->confirm($user, $data['code']);

        if ($codes === []) {
            return back()->with('error', 'That authenticator code is not valid.');
        }

        $this->login($request, $user);
        $request->session()->flash('recovery_codes', $codes);

        return redirect()->route('two-factor.recovery');
    }

    public function recovery(Request $request): View|RedirectResponse
    {
        $codes = $request->session()->pull('recovery_codes');
        if (! is_array($codes) || $codes === []) {
            return redirect()->route('dashboard');
        }

        return view('auth.recovery-codes', ['codes' => $codes]);
    }

    public function challenge(): View|RedirectResponse
    {
        if (! session()->has('pending_login_id')) {
            return redirect()->route('login');
        }

        return view('auth.two-factor-challenge');
    }

    public function verify(Request $request): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:20']]);
        $user = $this->pendingUser($request);

        if (! $this->twoFactor->verify($user, $data['code'])) {
            AuditLogger::record('mfa_failed', $user);

            return back()->with('error', 'That code is not valid.');
        }

        $this->login($request, $user);

        return redirect()->intended(route('dashboard'));
    }

    private function pendingUser(Request $request): User
    {
        $user = User::query()->find($request->session()->get('pending_login_id'));
        abort_unless($user && $user->is_active, 403);

        return $user;
    }

    private function login(Request $request, User $user): void
    {
        $request->session()->forget('pending_login_id');
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('mfa_passed', true);
        AuditLogger::record('login_succeeded', $user);
    }
}
