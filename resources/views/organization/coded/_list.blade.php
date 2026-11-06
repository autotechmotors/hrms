@php
    $columns = 4 + ($showCategory ? 1 : 0) + ($showExit ? 1 : 0);
@endphp
<div class="org-body" data-page="{{ $records->currentPage() }}" data-last="{{ $records->lastPage() }}" data-total="{{ $records->total() }}">
    <div class="org-cards">
        @forelse($records as $record)
            <article class="org-card">
                <div class="org-card-top">
                    <div>
                        <b>{{ $record->name }}</b>
                        <small>{{ $record->code }}</small>
                    </div>
                    <span class="b {{ $record->is_active ? 'ok' : '' }}">{{ $record->is_active ? 'Active' : 'Inactive' }}</span>
                </div>
                @if($showCategory || $showExit)
                    <div class="org-card-meta">
                        @if($showCategory)<span>{{ $record->employeeType?->name ?: 'No category' }}</span>@endif
                        @if($showExit)<span>{{ $record->is_exit ? 'Exit status' : 'Working status' }}</span>@endif
                    </div>
                @endif
                <div class="org-ops">
                    @permission($permission.'.edit')
                        <a class="btn sm" href="{{ route($route.'.edit', $record) }}">Edit</a>
                    @endpermission
                    @permission($permission.'.delete')
                        <button class="btn sm org-del" type="button" data-org-delete data-url="{{ route($route.'.destroy', $record) }}" data-name="{{ $record->name }}">Delete</button>
                    @endpermission
                </div>
            </article>
        @empty
            <p class="org-empty">Nothing here yet.</p>
        @endforelse
    </div>

    <div class="tw org-table">
        <table>
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Name</th>
                    @if($showCategory)<th>Category</th>@endif
                    @if($showExit)<th>Exit</th>@endif
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($records as $record)
                <tr>
                    <td><b>{{ $record->code }}</b></td>
                    <td>{{ $record->name }}</td>
                    @if($showCategory)<td>{{ $record->employeeType?->name ?: 'Not set' }}</td>@endif
                    @if($showExit)<td>{{ $record->is_exit ? 'Yes' : 'No' }}</td>@endif
                    <td><span class="b {{ $record->is_active ? 'ok' : '' }}">{{ $record->is_active ? 'Active' : 'Inactive' }}</span></td>
                    <td class="org-act">
                        @permission($permission.'.edit')
                            <a class="ib" href="{{ route($route.'.edit', $record) }}" title="Edit"><x-i name="pen" :size="14" /></a>
                        @endpermission
                        @permission($permission.'.delete')
                            <button class="ib org-del" type="button" title="Delete" data-org-delete data-url="{{ route($route.'.destroy', $record) }}" data-name="{{ $record->name }}"><x-i name="trash" :size="14" /></button>
                        @endpermission
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $columns }}" class="org-empty">Nothing here yet.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="pgn">
        <span>{{ $records->firstItem() ?? 0 }}–{{ $records->lastItem() ?? 0 }} of {{ $records->total() }}</span>
        <div>{{ $records->links() }}</div>
    </div>
</div>
