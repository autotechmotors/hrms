@extends('layouts.app')
@section('title', $title)
@section('content')
    <div class="ph">
        <div>
            <h1>{{ $title }}</h1>
            <p>{{ $summary }}</p>
        </div>
    </div>
    <section class="card">
        <div class="cb" style="color:var(--mu)">This screen is part of the workspace. Live records will appear here when the module is switched on.</div>
    </section>
@endsection
