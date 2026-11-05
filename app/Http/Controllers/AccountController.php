<?php

namespace App\Http\Controllers;

use App\Support\Navigation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function more(Request $request): View
    {
        $user = $request->user();
        $user->loadMissing('roles');

        return view('account.more', [
            'groups' => Navigation::visible($user),
            'roleLabel' => $user->roles->pluck('name')->filter()->unique()->join(', ') ?: 'Staff',
        ]);
    }

    public function edit(Request $request): View
    {
        return view('account.profile', [
            'account' => $request->user(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:160', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ]);

        $user->name = $data['name'];
        $user->email = $data['email'];
        if (filled($data['password'] ?? null)) {
            $user->password = $data['password'];
        }
        $user->save();

        return redirect()->route('profile.edit')->with('success', 'Profile updated.');
    }
}
