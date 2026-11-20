<div class="sg-history" data-live-list="salary-sheets" data-page="{{ $sheets->currentPage() }}" data-last="{{ $sheets->lastPage() }}">
    <div class="tw">
        <table class="sg-table">
            <thead>
            <tr>
                <th>{{ __('Sheet') }}</th>
                <th>{{ __('Dates') }}</th>
                <th>{{ __('People') }}</th>
                <th>{{ __('Net') }}</th>
                <th>{{ __('Status') }}</th>
                <th></th>
            </tr>
            </thead>
            <tbody data-list-rows>
            @forelse($sheets as $row)
                <tr>
                    @php
                        $badge = match ($row->status) {
                            'approved' => 'ok',
                            'rejected' => 'er',
                            default => 'wn',
                        };
                        $label = match ($row->status) {
                            'approved' => 'Approved',
                            'rejected' => 'Rejected',
                            default => 'Pending',
                        };
                    @endphp
                    <td>
                        <b>{{ $row->title }}</b>
                        <small>{{ $row->created_at?->format('d M Y, H:i') }}</small>
                        @if($row->status === 'rejected' && $row->decision_note)
                            <small>Reason: {{ $row->decision_note }}</small>
                        @endif
                    </td>
                    <td>{{ $row->starts_on->format('d M Y') }} – {{ $row->ends_on->format('d M Y') }}</td>
                    <td>{{ $row->lines_count }}</td>
                    <td class="sg-money">₹{{ number_format((float) $row->lines_sum_net, 2) }}</td>
                    <td><span class="b {{ $badge }}">{{ $label }}</span></td>
                    <td class="sg-ops">
                        @if($canDecide && $row->status === 'pending')
                            <button class="btn sm pr sg-approve" type="button" data-url="{{ route('salary-sheets.approve', $row) }}">Approve</button>
                            <button class="btn sm sg-reject" type="button" data-url="{{ route('salary-sheets.reject', $row) }}">Reject</button>
                        @endif
                        <a class="btn sm" href="{{ route('salary-sheets.show', $row) }}">View</a>
                        <a class="btn sm" href="{{ route('salary-sheets.excel', $row) }}">Excel</a>
                        <a class="btn sm" href="{{ route('salary-sheets.pdf', $row) }}">PDF</a>
                    </td>
                </tr>
            @empty
                <tr data-list-empty>
                    <td colspan="6" class="sg-empty">No salary request matches this list.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @include('partials.list-footer', ['paginator' => $sheets])
</div>
