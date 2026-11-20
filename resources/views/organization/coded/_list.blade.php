@php
    $columns = 3 + ($showCategory ? 1 : 0) + ($showExit ? 1 : 0) + ($showPeople ? 1 : 0);
@endphp
<div class="org-body" data-live-list="coded" data-page="{{ $records->currentPage() }}" data-last="{{ $records->lastPage() }}" data-total="{{ $records->total() }}">
    <div class="org-cards" data-list-rows>
        @forelse($records as $record)
            <article class="org-card">
                <div class="org-card-top">
                    <span class="org-code">{{ $record->code }}</span>
                    <div class="org-copy">
                        <b>{{ $record->name }}</b>
                        @if($record->description)
                            <small>{{ $record->description }}</small>
                        @endif
                    </div>
                    <span class="b {{ $record->is_active ? 'ok' : '' }}">{{ $record->is_active ? 'Active' : 'Inactive' }}</span>
                </div>
                @if($showCategory || $showExit || $showPeople)
                    <div class="org-card-meta">
                        @if($showPeople)<span>{{ (int) $record->employees_count }} {{ \Illuminate\Support\Str::plural('person', (int) $record->employees_count) }}</span>@endif
                        @if($showCategory)<span>{{ $record->employeeType?->name ?: 'No category' }}</span>@endif
                        @if($showExit)<span>{{ $record->is_exit ? 'Exit status' : 'Working status' }}</span>@endif
                    </div>
                @endif
                <div class="org-ops">
                    @permission($permission.'.edit')
                        <a class="btn sm" href="{{ route($route.'.edit', $record) }}"><x-i name="pen" :size="13" />{{ __('Edit') }}</a>
                    @endpermission
                    @permission($permission.'.delete')
                        <button class="btn sm org-del" type="button" data-org-delete data-url="{{ route($route.'.destroy', $record) }}" data-name="{{ $record->name }}"><x-i name="trash" :size="13" />{{ __('Delete') }}</button>
                    @endpermission
                </div>
            </article>
        @empty
            <p class="org-empty" data-list-empty>Nothing here yet.</p>
        @endforelse
    </div>

    <div class="tw org-table">
        <table>
            <thead>
                <tr>
                    <th>{{ $title }}</th>
                    @if($showCategory)<th>{{ __('Category') }}</th>@endif
                    @if($showExit)<th>{{ __('Exit') }}</th>@endif
                    @if($showPeople)<th>{{ __('People') }}</th>@endif
                    <th>{{ __('Status') }}</th>
                    <th class="org-act">Actions</th>
                </tr>
            </thead>
            <tbody data-list-rows>
            @forelse($records as $record)
                <tr>
                    <td class="org-main">
                        <div class="org-who">
                            <span class="org-code">{{ $record->code }}</span>
                            <div class="org-copy">
                                <b>{{ $record->name }}</b>
                                @if($record->description)
                                    <small>{{ $record->description }}</small>
                                @endif
                            </div>
                        </div>
                    </td>
                    @if($showCategory)<td>{{ $record->employeeType?->name ?: 'Not set' }}</td>@endif
                    @if($showExit)<td>{{ $record->is_exit ? 'Yes' : 'No' }}</td>@endif
                    @if($showPeople)<td>{{ (int) $record->employees_count }}</td>@endif
                    <td><span class="b {{ $record->is_active ? 'ok' : '' }}">{{ $record->is_active ? 'Active' : 'Inactive' }}</span></td>
                    <td class="org-act">
                        <div class="org-ops">
                            @permission($permission.'.edit')
                                <a class="btn sm" href="{{ route($route.'.edit', $record) }}"><x-i name="pen" :size="13" />{{ __('Edit') }}</a>
                            @endpermission
                            @permission($permission.'.delete')
                                <button class="btn sm org-del" type="button" data-org-delete data-url="{{ route($route.'.destroy', $record) }}" data-name="{{ $record->name }}"><x-i name="trash" :size="13" />{{ __('Delete') }}</button>
                            @endpermission
                        </div>
                    </td>
                </tr>
            @empty
                <tr data-list-empty>
                    <td colspan="{{ $columns }}" class="org-empty">Nothing here yet.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @include('partials.list-footer', ['paginator' => $records])
</div>
