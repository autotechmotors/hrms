@if($people->isEmpty())
    <p class="sg-empty">No active people in this department.</p>
@else
    <div class="sg-people-bar">
        <label class="sg-all">
            <input type="checkbox" id="sgAll" checked>
            <span>Everyone ready for salary</span>
        </label>
        <b id="sgCount">0 selected</b>
        <input class="in" id="sgFind" type="search" placeholder="Find a person" autocomplete="off">
    </div>
    <div class="tw sg-people-scroll">
        <table class="sg-table sg-pick-table">
            <thead>
            <tr>
                <th class="sg-check"></th>
                <th>{{ __('Code') }}</th>
                <th>{{ __('Name') }}</th>
                <th>{{ __('Pay') }}</th>
            </tr>
            </thead>
            <tbody>
            @foreach($people as $person)
                @php
                    $ready = $person->isHoursSalary() && (float) ($person->currentSalary?->gross_monthly ?? 0) > 0;
                    $payNote = match (true) {
                        $person->isPieceSalary() => 'Piece based · attendance only',
                        ! $person->isHoursSalary() => 'Set salary type on profile',
                        (float) ($person->currentSalary?->gross_monthly ?? 0) <= 0 => 'Hours based · amount not set',
                        default => 'Hours based · ₹'.number_format((float) $person->currentSalary->gross_monthly, 2),
                    };
                @endphp
                <tr class="{{ $ready ? '' : 'sg-skip' }}" @unless($ready) title="{{ $payNote }}" @endunless>
                    <td class="sg-check">
                        @if($ready)
                            <input class="sg-pick" type="checkbox" name="employee_ids[]" value="{{ $person->id }}" checked>
                        @else
                            <input type="checkbox" disabled>
                        @endif
                    </td>
                    <td><b>{{ $person->employee_code }}</b></td>
                    <td>{{ $person->full_name }}</td>
                    <td><small class="sg-pay-note">{{ $payNote }}</small></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <p class="sg-people-hint">Piece based people stay off this list for generate. Hours based people need a monthly amount on Hours &amp; pay.</p>
@endif
