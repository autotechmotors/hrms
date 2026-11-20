@if($people->isEmpty())
    <p class="sg-empty">No active people in this department.</p>
@else
    <div class="sg-people-bar">
        <label class="sg-all">
            <input type="checkbox" id="sgAll" checked>
            <span>Everyone in this department</span>
        </label>
        <b id="sgCount">{{ $people->count() }} selected</b>
        <input class="in" id="sgFind" type="search" placeholder="Find a person" autocomplete="off">
    </div>
    <div class="tw sg-people-scroll">
        <table class="sg-table sg-pick-table">
            <thead>
            <tr>
                <th class="sg-check"></th>
                <th>{{ __('Code') }}</th>
                <th>{{ __('Name') }}</th>
            </tr>
            </thead>
            <tbody>
            @foreach($people as $person)
                <tr>
                    <td class="sg-check"><input class="sg-pick" type="checkbox" name="employee_ids[]" value="{{ $person->id }}" checked></td>
                    <td><b>{{ $person->employee_code }}</b></td>
                    <td>{{ $person->full_name }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif
