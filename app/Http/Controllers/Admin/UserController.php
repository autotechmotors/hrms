<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserRequest;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use App\Services\Users\UserAdminService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(private readonly UserAdminService $users)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);
        $users = User::query()
            ->with('roles')
            ->visibleTo($request->user())
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = '%'.trim((string) $request->query('q')).'%';
                $query->where(fn ($inner) => $inner->where('name', 'like', $term)->orWhere('email', 'like', $term));
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.users.index', compact('users'));
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        return view('admin.users.form', ['account' => null] + $this->options());
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $this->users->save(null, $this->payload($request), $request->input('roles', []), $request->input('scopes', []), $request->user());

        return redirect()->route('users.index')->with('success', 'User saved.');
    }

    public function edit(User $user): View
    {
        $this->authorize('update', $user);

        return view('admin.users.form', ['account' => $user->load('roles', 'scopes')] + $this->options());
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $this->users->save($user, $this->payload($request), $request->input('roles', []), $request->input('scopes', []), $request->user());

        return redirect()->route('users.index')->with('success', 'User updated.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);
        $this->users->deactivate($user, request()->user());

        return back()->with('success', 'User deactivated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(UserRequest $request): array
    {
        return [
            'name' => $request->string('name')->toString(),
            'email' => $request->string('email')->lower()->toString(),
            'password' => $request->input('password'),
            'is_active' => $request->boolean('is_active'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function options(): array
    {
        $actor = request()->user();

        return [
            'roles' => Role::query()
                ->where('slug', '!=', 'employee')
                ->when(! $actor->isSuperAdmin(), fn ($query) => $query->where('slug', '!=', 'super-admin'))
                ->orderBy('name')
                ->get(),
            'companies' => Company::query()->visibleTo($actor)->orderBy('name')->get(),
            'branches' => Branch::query()->visibleTo($actor)->orderBy('name')->get(),
            'departments' => Department::query()->visibleTo($actor)->orderBy('name')->get(),
            'employees' => Employee::query()->visibleTo($actor)->orderBy('first_name')->get(),
        ];
    }
}
