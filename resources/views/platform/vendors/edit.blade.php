@extends('platform.layout')

@section('title', 'Edit Vendor')

@section('content')
    <h1 class="text-3xl font-bold">Edit vendor</h1>
    <form method="POST" action="{{ route('platform.vendors.update', $vendor) }}" class="mt-6 rounded border border-slate-800 bg-slate-900 p-5">
        @method('PUT')
        @include('platform.vendors._form', ['submitLabel' => 'Save changes'])
    </form>
@endsection
