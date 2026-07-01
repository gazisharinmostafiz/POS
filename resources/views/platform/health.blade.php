@extends('platform.layout')

@section('title', 'System Health')

@section('content')
    <h1 class="text-3xl font-bold">System health</h1>
    <section class="mt-6 rounded-lg border border-slate-800 bg-slate-900 p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-sm uppercase tracking-widest text-slate-500">Overall status</p>
                <p class="mt-1 text-2xl font-black {{ $health['status'] === 'ok' ? 'text-emerald-300' : 'text-red-300' }}">{{ strtoupper($health['status']) }}</p>
            </div>
            <div class="text-right text-sm text-slate-400">
                <p>Version {{ $health['version'] }}</p>
                <p>Checked {{ $health['checked_at'] }}</p>
            </div>
        </div>

        <div class="mt-6 grid gap-3 md:grid-cols-2">
            @foreach ($health['checks'] as $name => $check)
                <article class="rounded border border-slate-800 bg-slate-950 p-4">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="font-bold capitalize">{{ str_replace('_', ' ', $name) }}</h2>
                        <span class="rounded px-2 py-1 text-xs font-bold {{ $check['status'] === 'ok' ? 'bg-emerald-950 text-emerald-300' : ($check['status'] === 'maintenance' ? 'bg-amber-950 text-amber-300' : 'bg-red-950 text-red-300') }}">
                            {{ strtoupper($check['status']) }}
                        </span>
                    </div>
                    @if (! empty($check['message']))
                        <p class="mt-2 text-sm text-slate-400">{{ $check['message'] }}</p>
                    @endif
                    @if ($name === 'app')
                        <p class="mt-2 text-sm text-slate-400">{{ $check['service'] }} {{ $check['version'] }} in {{ $check['environment'] }}</p>
                    @endif
                </article>
            @endforeach
        </div>
    </section>
@endsection
