@extends('platform.layout')

@section('title', 'Create Vendor')

@section('content')
    <h1 class="text-3xl font-bold">Create vendor</h1>
    <form method="POST" action="{{ route('platform.vendors.store') }}" class="mt-6 rounded border border-slate-800 bg-slate-900 p-5">
        @include('platform.vendors._form', ['submitLabel' => 'Create vendor'])
    </form>
@endsection
