@extends('platform.layout')

@section('title', 'Maintenance')

@section('content')
    <h1 class="text-3xl font-bold">Maintenance</h1>
    <p class="mt-2 text-slate-400">Use this during release windows and deployment verification.</p>

    <section class="mt-6 rounded-lg border border-slate-800 bg-slate-900 p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-sm uppercase tracking-widest text-slate-500">Current state</p>
                <p class="mt-1 text-2xl font-black {{ $maintenance['enabled'] ? 'text-amber-300' : 'text-emerald-300' }}">
                    {{ $maintenance['enabled'] ? 'Enabled' : 'Disabled' }}
                </p>
                <p class="mt-2 text-slate-300">{{ $maintenance['message'] }}</p>
            </div>
            @if ($maintenance['updated_at'])
                <p class="text-sm text-slate-500">Updated {{ $maintenance['updated_at'] }}</p>
            @endif
        </div>

        <form method="POST" action="{{ route('platform.maintenance.update') }}" class="mt-6 grid gap-4">
            @csrf
            <label class="block">
                <span class="text-sm font-bold text-slate-300">Message</span>
                <input name="message" value="{{ old('message', $maintenance['message']) }}" class="mt-1 w-full rounded border border-slate-700 bg-slate-950 px-3 py-2 text-white">
            </label>
            <div class="flex flex-wrap gap-3">
                <button name="enabled" value="1" class="rounded bg-amber-400 px-4 py-2 font-bold text-slate-950">Enable maintenance</button>
                <button name="enabled" value="0" class="rounded bg-emerald-500 px-4 py-2 font-bold text-slate-950">Disable maintenance</button>
            </div>
        </form>
    </section>
@endsection
