@extends('errors.layout')

@section('code', '419')
@section('title', __('errors.419.title'))
@section('message', __('errors.419.message'))
@section('actions')
    <a class="button" href="{{ url()->previous() }}">{{ __('errors.reload') }}</a>
@endsection
