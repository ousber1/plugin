@extends('account._layout')
@section('title', __('Mes commandes'))
@section('account')
<h1>{{ __('Mes commandes') }}</h1>
<div class="card">@include('account._orders-table', ['orders' => $orders]) {{ $orders->links('partials.pagination') }}</div>
@endsection
