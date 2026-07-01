@extends('platform.layout')

@section('title', 'Vendor Details')

@section('content')
    <div class="mb-6 flex items-start justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold">{{ $vendor->name }}</h1>
            <p class="mt-2 text-slate-400">Vendor details and platform lifecycle controls.</p>
        </div>
        <div class="flex gap-3">
            <a href="{{ route('platform.vendors.settings.edit', $vendor) }}" class="rounded border border-slate-700 px-4 py-2 text-slate-200">Restaurant settings</a>
            <a href="{{ route('platform.vendors.edit', $vendor) }}" class="rounded border border-slate-700 px-4 py-2 text-slate-200">Edit vendor</a>
        </div>
    </div>

    <section class="rounded border border-slate-800 bg-slate-900 p-5">
        <dl class="grid gap-4 md:grid-cols-2">
            <div>
                <dt class="text-sm text-slate-400">Slug</dt>
                <dd class="mt-1 font-medium">{{ $vendor->slug }}</dd>
            </div>
            <div>
                <dt class="text-sm text-slate-400">Status</dt>
                <dd class="mt-1 font-medium">{{ $vendor->is_active ? 'Active' : 'Suspended' }}</dd>
            </div>
            <div>
                <dt class="text-sm text-slate-400">Custom domain</dt>
                <dd class="mt-1 font-medium">{{ $vendor->domain ?? '-' }}</dd>
            </div>
            <div>
                <dt class="text-sm text-slate-400">Subdomain</dt>
                <dd class="mt-1 font-medium">{{ $vendor->subdomain ?? '-' }}</dd>
            </div>
        </dl>

        <div class="mt-6 flex gap-3">
            @if ($vendor->is_active)
                <form method="POST" action="{{ route('platform.vendors.suspend', $vendor) }}">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="rounded bg-red-500 px-4 py-2 font-semibold text-white">Suspend vendor</button>
                </form>
            @else
                <form method="POST" action="{{ route('platform.vendors.reactivate', $vendor) }}">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="rounded bg-emerald-500 px-4 py-2 font-semibold text-white">Reactivate vendor</button>
                </form>
            @endif
        </div>
    </section>

    <section class="mt-8 rounded border border-slate-800 bg-slate-900 p-5">
        <h2 class="text-xl font-semibold">Vendor audit log</h2>
        <div class="mt-4 divide-y divide-slate-800">
            @forelse ($auditLogs as $log)
                <div class="py-3 text-sm">
                    <span class="font-medium">{{ $log->event }}</span>
                    <span class="text-slate-400">by user #{{ $log->user_id ?? 'system' }} on {{ $log->created_at->toDateTimeString() }}</span>
                </div>
            @empty
                <p class="text-sm text-slate-400">No vendor audit entries yet.</p>
            @endforelse
        </div>
    </section>
@endsection
