@extends('errors.layout')

@section('code', '429')
@section('title', __('errors.429.title'))
@section('message', $exception instanceof \Illuminate\Http\Exceptions\ThrottleRequestsException
    ? \App\Security\ThrottledResponse::message($exception)
    : __('rate_limit.too_many', ['minutes' => 1]))
@section('actions')
    <a class="button" href="{{ url()->previous() }}">{{ __('errors.back') }}</a>
@endsection
