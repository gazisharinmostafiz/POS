@extends('platform.layout')

@section('title', 'Platform Overview')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold">Platform overview</h1>
            <p class="mt-2 text-slate-400">High-level platform owner dashboard.</p>
        </div>
        <a href="{{ route('platform.vendors.create') }}" class="rounded bg-cyan-400 px-4 py-2 font-semibold text-slate-950">Create vendor</a>
    </div>

    <div class="grid gap-4 md:grid-cols-3">
        <section class="rounded border border-slate-800 bg-slate-900 p-5">
            <p class="text-sm text-slate-400">Total vendors</p>
            <p class="mt-2 text-3xl font-bold">{{ $totalVendors }}</p>
        </section>
        <section class="rounded border border-slate-800 bg-slate-900 p-5">
            <p class="text-sm text-slate-400">Active vendors</p>
            <p class="mt-2 text-3xl font-bold">{{ $activeVendors }}</p>
        </section>
        <section class="rounded border border-slate-800 bg-slate-900 p-5">
            <p class="text-sm text-slate-400">Suspended vendors</p>
            <p class="mt-2 text-3xl font-bold">{{ $suspendedVendors }}</p>
        </section>
    </div>

    <section class="mt-8 rounded border border-slate-800 bg-slate-900 p-5">
        <h2 class="text-xl font-semibold">Recent audit activity</h2>
        <div class="mt-4 divide-y divide-slate-800">
            @forelse ($recentAuditLogs as $log)
                <div class="py-3 text-sm">
                    <span class="font-medium">{{ $log->event }}</span>
                    <span class="text-slate-400">by user #{{ $log->user_id ?? 'system' }} on {{ $log->created_at->toDateTimeString() }}</span>
                </div>
            @empty
                <p class="text-sm text-slate-400">No audit activity yet.</p>
            @endforelse
        </div>
    </section>
@endsection
