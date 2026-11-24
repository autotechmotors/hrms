@extends('layouts.app')
@section('title', 'Settings')
@section('content')
    <div class="st-page">
        <div class="ph">
            <div>
                <h1>{{ __('Settings') }}</h1>
                <p>Company rules for auto-block and for when OUT closes. Super Admin and HR Admin are never auto-blocked.</p>
            </div>
            <div class="ac">
                <button class="btn pr" type="submit" form="settingsForm">Save settings</button>
            </div>
        </div>

        @include('operations.errors')

        <div class="st-grid">
            <form id="settingsForm" class="card ef-sec rl-page" method="post" action="{{ route('settings.update') }}">
                @csrf
                @method('put')
                <header>
                    <div>
                        <h3>{{ __('Auto-block without in / out') }}</h3>
                        <p>Anyone else with no punch for the set days is marked Blocked.</p>
                    </div>
                </header>
                <div class="cb">
                    <x-flag-switch
                        name="block_inactive_enabled"
                        label="Turn this rule on"
                        hint="Off means nobody is auto-blocked."
                        :checked="$setting->block_inactive_enabled"
                    />
                    <div class="st-days">
                        <x-text-field
                            name="block_inactive_days"
                            label="Days without in / out"
                            type="number"
                            :value="old('block_inactive_days', $setting->block_inactive_days)"
                            required
                        />
                        <p class="form-text">Full days with no in and no out. Unblock later from Employees.</p>
                    </div>
                    <ul class="st-notes">
                        <li>The count starts from the last punch, or from the joining date when there is none. Today is not counted until it is over.</li>
                        <li>Saving while the rule is on blocks everyone already over the limit. The scheduled job does the same once each day.</li>
                        <li>Super Admin and HR Admin stay active even when they have no punches.</li>
                    </ul>
                    <div class="st-days">
                        <x-text-field
                            name="out_inactive_minutes"
                            label="Minutes before out"
                            type="number"
                            :value="old('out_inactive_minutes', $setting->out_inactive_minutes)"
                            required
                            min="0"
                            max="240"
                        />
                        <p class="form-text">A machine scan becomes OUT only inside this many minutes before the scheduled end, or with an approved gate pass. An earlier scan stays an in. IN can be at any time. Late minutes are still cut after grace.</p>
                    </div>
                </div>
            </form>

            <aside class="st-side">
                <section class="card st-stat">
                    <small>Rule</small>
                    <b class="{{ $setting->block_inactive_enabled ? 'on' : '' }}">{{ $setting->block_inactive_enabled ? 'On' : 'Off' }}</b>
                    <span>{{ $setting->block_inactive_days }} days without a punch</span>
                </section>
                <section class="card st-stat">
                    <small>Overdue now</small>
                    <b>{{ $overdueTotal }}</b>
                    <span>{{ $setting->block_inactive_enabled ? 'Blocked on save, and once each day by the scheduled job.' : 'Shown for review. The rule is off.' }}</span>
                </section>
            </aside>
        </div>

        <section class="card ef-sec st-idle" id="stIdle" data-idle-count="{{ $overdueTotal }}">
            <header>
                <div>
                    <h3>{{ __('Currently overdue') }}</h3>
                    <p>
                        @if($setting->block_inactive_enabled)
                            Already past {{ $setting->block_inactive_days }} days. Saving blocks them. The daily job does the same.
                        @else
                            Turn the rule on and save to start blocking.
                        @endif
                    </p>
                </div>
                <span class="b {{ $idle->total() === 0 ? 'ok' : 'wn' }}" id="stCount">{{ $idle->total() }}</span>
            </header>
            <div class="cb">
                @if($setting->block_inactive_enabled && $overdueTotal > 0)
                    <p class="st-alert"><b>{{ $overdueTotal }} people</b> are past {{ $setting->block_inactive_days }} days. Save blocks them now. The daily job blocks anyone who crosses the limit later. Super Admin and HR Admin stay active.</p>
                @endif
                <form id="stFind" class="st-find" method="get" action="{{ route('settings.edit') }}">
                    <label for="stOverdue">Search overdue</label>
                    <input id="stOverdue" class="form-control" type="search" name="overdue" value="{{ $overdueTerm }}" placeholder="Name or employee code" autocomplete="off">
                </form>
                @include('admin.settings._idle')
            </div>
        </section>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            const root = document.querySelector('.st-page');
            const form = document.getElementById('settingsForm');
            if (!root || !form) return;

            let timer = null;
            let request = null;

            const load = (url, term) => {
                const target = new URL(url, window.location.origin);
                target.searchParams.set('partial', '1');
                if (term) target.searchParams.set('overdue', term);
                else target.searchParams.delete('overdue');
                if (request) request.abort();
                request = $.ajax({
                    url: target.toString(),
                    headers: {'X-Requested-With': 'XMLHttpRequest'},
                    success(html) {
                        const box = document.getElementById('stResults');
                        if (!box) return;
                        box.outerHTML = html;
                        const next = document.getElementById('stResults');
                        const badge = document.getElementById('stCount');
                        if (badge && next) {
                            badge.textContent = next.dataset.matched || '0';
                            badge.classList.toggle('ok', badge.textContent === '0');
                            badge.classList.toggle('wn', badge.textContent !== '0');
                        }
                        const clean = new URL(target);
                        clean.searchParams.delete('partial');
                        history.replaceState(null, '', clean.pathname + clean.search);
                    },
                    error(xhr) {
                        if (xhr.statusText === 'abort') return;
                        window.toast('Could not refresh the overdue list.', 'er');
                    }
                });
            };

            root.addEventListener('input', (event) => {
                if (event.target.id !== 'stOverdue') return;
                window.clearTimeout(timer);
                const term = event.target.value.trim();
                timer = window.setTimeout(() => {
                    const url = new URL(window.location.href);
                    url.searchParams.delete('page');
                    load(url.toString(), term);
                }, 250);
            });

            root.addEventListener('submit', (event) => {
                if (event.target.id === 'stFind') event.preventDefault();
            });

            root.addEventListener('click', (event) => {
                const link = event.target.closest('#stResults .el-pager a');
                if (!link) return;
                event.preventDefault();
                const field = document.getElementById('stOverdue');
                load(link.href, field ? field.value.trim() : '');
            });

            form.addEventListener('submit', (event) => {
                const toggle = document.getElementById('block_inactive_enabled-switch');
                if (!toggle || !toggle.checked || form.dataset.confirmed === '1') return;
                event.preventDefault();
                window.askConfirm(
                    @json(__('Saving with the rule on blocks everyone already over the days you set. Super Admin and HR Admin stay active.')),
                    {label: @json(__('Save and block')), danger: true}
                ).then((ok) => {
                    if (!ok) return;
                    form.dataset.confirmed = '1';
                    form.requestSubmit ? form.requestSubmit() : form.submit();
                });
            });
        })();
    </script>
@endpush
