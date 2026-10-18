@extends('errors.layout')

@section('code', '419')
@section('title', __('errors.419.title'))
@section('message', __('errors.419.message'))
@section('actions')
    <a class="button" href="{{ \App\Security\SafeBackUrl::get() }}">{{ __('errors.reload') }}</a>
@endsection
