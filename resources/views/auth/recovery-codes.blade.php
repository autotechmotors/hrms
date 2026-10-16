@extends('layouts.app')
@section('title', 'Recovery codes')
@section('content')
    <div class="card">
        <div class="card-body">
            <h1 class="h4">Save these recovery codes</h1>
            <p class="text-secondary">Each code works once. Store them somewhere private. They will not be shown again.</p>
            <ul class="font-monospace">
                @foreach($codes as $code)
                    <li>{{ $code }}</li>
                @endforeach
            </ul>
            <a class="btn btn-primary" href="{{ route('dashboard') }}">Continue</a>
        </div>
    </div>
@endsection
