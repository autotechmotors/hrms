@extends('layouts.app')
@section('title', 'Holidays')
@section('content')
    <div class="ph">
        <div>
            <h1>Holidays</h1>
            <p>Public holidays are non-working. Restricted and optional stay on the calendar and follow each leave type’s setting.</p>
        </div>
        <div class="ac">
            <a class="btn" href="{{ route('holidays.index', ['year' => $year - 1]) }}">{{ $year - 1 }}</a>
            <a class="btn" href="{{ route('holidays.index', ['year' => $year + 1]) }}">{{ $year + 1 }}</a>
        </div>
    </div>
    @include('operations.errors')
    @if($canEdit)
        <section class="card" style="padding:16px;margin-bottom:16px">
            <form method="post" action="{{ route('holidays.store') }}" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end">
                @csrf
                <label>Date<br><input class="in" type="date" name="holiday_date" required value="{{ old('holiday_date') }}"></label>
                <label>Name<br><input class="in" name="name" required value="{{ old('name') }}"></label>
                <label>Kind<br>
                    <select class="in" name="kind">
                        <option value="public">Public</option>
                        <option value="restricted">Restricted</option>
                        <option value="optional">Optional</option>
                    </select>
                </label>
                <button class="btn pr" type="submit">Add holiday</button>
            </form>
        </section>
    @endif
    <section class="card">
        <header>{{ $year }}</header>
        <div class="tw">
            <table>
                <thead><tr><th>Date</th><th>Name</th><th>Kind</th><th></th></tr></thead>
                <tbody>
                @forelse($holidays as $holiday)
                    <tr>
                        <td>{{ $holiday->holiday_date->format('D, d M Y') }}</td>
                        <td>{{ $holiday->name }}</td>
                        <td><span class="b {{ $holiday->kind === 'public' ? 'ok' : 'wn' }}">{{ $holiday->kind }}</span></td>
                        <td>
                            @if($canEdit)
                                <form method="post" action="{{ route('holidays.destroy', $holiday) }}" data-confirm="Remove {{ $holiday->name }}?">
                                    @csrf @method('delete')
                                    <button class="btn" type="submit">Remove</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" style="text-align:center;color:var(--mu);padding:32px">No holidays for {{ $year }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
