@extends('layouts.app')
@section('title', $role ? __('Edit :name', ['name' => $role->name]) : __('New role'))
@section('content')
    @php
        $selected = old('permissions', $role ? $role->permissions->pluck('id')->all() : []);
        $isSuper = ($role->slug ?? '') === 'super-admin';
    @endphp

    <div class="ph">
        <div>
            <x-back :href="route('roles.index')" label="Roles" />
            <h1>{{ $role ? __('Edit :name', ['name' => $role->name]) : __('New role') }}</h1>
            <p>Name the role, then set parent / child rules and what it can open.</p>
        </div>
        <div class="ac">
            <a class="btn" href="{{ route('roles.index') }}">{{ __('Cancel') }}</a>
            <button class="btn pr" type="submit" form="roleForm">Save role</button>
        </div>
    </div>

    <form id="roleForm" class="ef rl-page" method="post" action="{{ $role ? route('roles.update', $role) : route('roles.store') }}">
        @csrf
        @if($role) @method('put') @endif

        <div class="ef-grid">
            <div class="ef-main">
                <section class="card ef-sec">
                    <header>
                        <div>
                            <h3>{{ __('Role') }}</h3>
                            <p>How this role is named in Users and Employees</p>
                        </div>
                    </header>
                    <div class="cb">
                        <x-text-field name="name" label="Role name" :value="$role->name ?? ''" required placeholder="Manager" />
                        <div class="g c2">
                            <x-text-field name="slug" label="Slug" :value="$role->slug ?? ''" required :readonly="$role?->is_system" placeholder="manager" />
                            <x-text-field name="description" label="Description" :value="$role->description ?? ''" placeholder="Team lead who approves gate passes" />
                        </div>
                    </div>
                </section>

                <section class="card ef-sec">
                    <header>
                        <div>
                            <h3>{{ __('Hierarchy') }}</h3>
                            <p>Parent required on employee form, children in the manager list</p>
                        </div>
                    </header>
                    <div class="cb rl-rules">
                        <x-flag-switch
                            name="has_parent"
                            label="It has parent"
                            hint="On = employee form ma Parent select required."
                            :checked="$role->has_parent ?? false"
                        />
                        <x-flag-switch
                            name="has_child"
                            label="It has child"
                            hint="On = aa role na people Parent list ma aavse."
                            :checked="$role->has_child ?? false"
                        />
                        <x-flag-switch
                            name="is_active"
                            label="Status"
                            :checked="$role->is_active ?? true"
                            on="Active"
                            off="Inactive"
                            :disabled="$isSuper"
                        />
                        <x-flag-switch
                            name="requires_mfa"
                            label="Require authenticator"
                            hint="On = this role must complete MFA before using HRMS."
                            :checked="$role->requires_mfa ?? false"
                        />
                    </div>
                </section>

                <section class="card ef-sec">
                    <header>
                        <div>
                            <h3>{{ __('Permissions') }}</h3>
                            <p>{{ $isSuper ? 'Super Admin always has every permission' : 'What this role can open in HRMS' }}</p>
                        </div>
                    </header>
                    <div class="cb">
                        @if($isSuper)
                            <p class="form-text mb-0">Permissions added later are included automatically.</p>
                        @else
                            <div class="rl-perms">
                                @foreach($groups as $group => $permissions)
                                    <article>
                                        <h4>{{ ucwords(str_replace('_', ' ', $group)) }}</h4>
                                        <div>
                                            @foreach($permissions as $permission)
                                                <label class="rl-perm">
                                                    <input type="checkbox" name="permissions[]" value="{{ $permission->id }}" @checked(in_array($permission->id, $selected))>
                                                    <span>{{ $permission->name }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </section>
            </div>

            <aside class="ef-side">
                <section class="card ef-sec">
                    <header>
                        <div>
                            <h3>{{ __('How flags work') }}</h3>
                        </div>
                    </header>
                    <div class="cb">
                        <ul class="rl-help">
                            <li><b>Has parent</b> — employee with this role must pick a reporting manager.</li>
                            <li><b>Has child</b> — this role’s people appear in the Parent list.</li>
                            <li><b>Status</b> — inactive roles cannot be assigned to new users.</li>
                            <li>Manager and Director start with both flags on.</li>
                        </ul>
                    </div>
                </section>
            </aside>
        </div>

        <div class="ef-bar">
            <a class="btn" href="{{ route('roles.index') }}">{{ __('Cancel') }}</a>
            <button class="btn pr" type="submit">Save role</button>
        </div>
    </form>
@endsection

@push('scripts')
<script>
(function () {
    document.querySelectorAll('[data-rl-switch]').forEach(function (input) {
        var text = input.parentElement.querySelector('.rl-txt');
        var sync = function () {
            if (text) text.textContent = input.checked ? input.dataset.on : input.dataset.off;
        };
        input.addEventListener('change', sync);
        sync();
    });

    var name = document.getElementById('name');
    var slug = document.getElementById('slug');
    if (name && slug && !slug.readOnly) {
        name.addEventListener('input', function () {
            if (slug.dataset.locked || slug.value && slug.value !== slug.dataset.auto) return;
            slug.value = name.value.toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
            slug.dataset.auto = slug.value;
        });
        slug.addEventListener('input', function () { slug.dataset.locked = '1'; });
    }
})();
</script>
@endpush
