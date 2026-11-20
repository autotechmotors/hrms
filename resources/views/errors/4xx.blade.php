@extends('errors::minimal')

@section('code', (string) (isset($exception) && method_exists($exception, 'getStatusCode') ? $exception->getStatusCode() : 400))
@section('title', 'Error')
@section('message', 'This page could not be opened.')
