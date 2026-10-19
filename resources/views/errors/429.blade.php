@extends('errors.layout')

@section('code', '429')
@section('title', __('errors.429.title'))
@section('message', $exception instanceof \Illuminate\Http\Exceptions\ThrottleRequestsException
    ? \App\Security\ThrottledResponse::message($exception)
    : __('rate_limit.too_many', ['minutes' => 1]))
@section('actions')
    <a class="button" href="{{ \App\Security\SafeBackUrl::get() }}">{{ __('errors.back') }}</a>
    {{-- F-002 AC-39: public pages link to the Rakyt home page. --}}
    <a class="button" href="{{ url('/') }}">{{ __('errors.home') }}</a>
@endsection
