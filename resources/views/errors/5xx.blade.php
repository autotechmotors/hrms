@extends('errors::minimal')

@section('code', (string) (isset($exception) && method_exists($exception, 'getStatusCode') ? $exception->getStatusCode() : 500))
@section('title', 'Error')
@section('message', 'Something went wrong on our side. Try again in a moment.')
