@extends('errors.layout')

@section('code', '419')
@section('title', 'Sesi Anda sudah habis')
@section('message', 'Demi keamanan, halaman ini kedaluwarsa. Muat ulang halaman lalu coba lagi.')
@section('actions')
    <a class="button" href="{{ url()->previous() }}">Muat ulang</a>
@endsection
