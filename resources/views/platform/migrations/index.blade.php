@extends('platform.layout')

@section('title', 'Server Migration')

@section('content')
    <div class="mb-6">
        <p class="text-sm font-semibold uppercase tracking-widest text-cyan-300">Platform owner</p>
        <h1 class="mt-2 text-3xl font-bold">Server migration assistant</h1>
        <p class="mt-2 text-slate-400">Generate a private migration package for moving PosLAB to a new server.</p>
    </div>

    @if ($errors->any())
        <div class="mb-6 rounded border border-red-700 bg-red-950 px-4 py-3 text-sm text-red-200">
            {{ $errors->first() }}
        </div>
    @endif

    <section class="mb-6 rounded border border-slate-800 bg-slate-900 p-5">
        <h2 class="text-xl font-semibold">Preflight checklist</h2>
        <ul class="mt-4 space-y-2 text-sm text-slate-300">
            @foreach ($preflight as $item)
                <li class="rounded border border-slate-800 bg-slate-950 px-3 py-2">{{ $item }}</li>
            @endforeach
        </ul>
        <form method="POST" action="{{ route('platform.migrations.store') }}" class="mt-5">
            @csrf
            <button class="rounded bg-cyan-400 px-4 py-2 font-semibold text-slate-950">Generate migration package</button>
        </form>
    </section>

    <div class="mb-6 grid gap-6 lg:grid-cols-2">
        <section class="rounded border border-slate-800 bg-slate-900 p-5">
            <h2 class="text-xl font-semibold">Maintenance mode placeholder</h2>
            <p class="mt-2 text-sm text-slate-400">This logs intent only. It does not run artisan down/up yet.</p>
            <form method="POST" action="{{ route('platform.migrations.maintenance') }}" class="mt-4 flex gap-3">
                @csrf
                <button name="enabled" value="1" class="rounded border border-yellow-700 px-3 py-2 text-yellow-200">Log enable</button>
                <button name="enabled" value="0" class="rounded border border-slate-700 px-3 py-2">Log disable</button>
            </form>
        </section>

        <section class="rounded border border-slate-800 bg-slate-900 p-5">
            <h2 class="text-xl font-semibold">Remote migration placeholder</h2>
            <p class="mt-2 text-sm text-slate-400">Credentials are encrypted at rest and never shown after save.</p>
            <form method="POST" action="{{ route('platform.migrations.credentials') }}" class="mt-4 grid gap-3">
                @csrf
                <input name="name" required placeholder="Profile name" class="rounded border border-slate-700 bg-slate-950 px-3 py-2">
                <input name="host" placeholder="Host" class="rounded border border-slate-700 bg-slate-950 px-3 py-2">
                <input name="port" type="number" placeholder="Port" class="rounded border border-slate-700 bg-slate-950 px-3 py-2">
                <input name="username" placeholder="Username" class="rounded border border-slate-700 bg-slate-950 px-3 py-2">
                <select name="auth_type" class="rounded border border-slate-700 bg-slate-950 px-3 py-2">
                    <option value="password">Password</option>
                    <option value="private_key">Private key</option>
                </select>
                <input name="password" type="password" placeholder="Password" class="rounded border border-slate-700 bg-slate-950 px-3 py-2">
                <textarea name="private_key" rows="3" placeholder="Private key" class="rounded border border-slate-700 bg-slate-950 px-3 py-2"></textarea>
                <button class="rounded bg-cyan-400 px-4 py-2 font-semibold text-slate-950">Save encrypted credentials</button>
            </form>
            <div class="mt-4 space-y-2 text-sm">
                @foreach ($credentials as $credential)
                    <div class="rounded border border-slate-800 bg-slate-950 px-3 py-2">
                        {{ $credential->name }} - {{ $credential->host ?: 'host pending' }} - secrets hidden
                    </div>
                @endforeach
            </div>
        </section>
    </div>

    <section class="rounded border border-slate-800 bg-slate-900 p-5">
        <h2 class="text-xl font-semibold">Migration log</h2>
        <div class="mt-4 overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="text-slate-400">
                    <tr>
                        <th class="py-2">Version</th>
                        <th>Status</th>
                        <th>Checksum</th>
                        <th>Backup</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse ($migrations as $migration)
                        <tr>
                            <td class="py-3">{{ $migration->version }}</td>
                            <td>{{ $migration->status }}</td>
                            <td class="font-mono text-xs">{{ $migration->checksum ?: '-' }}</td>
                            <td>{{ $migration->backup_id ?: '-' }}</td>
                            <td class="space-x-2 text-right">
                                @if ($migration->status === 'completed')
                                    <a href="{{ route('platform.migrations.download', $migration) }}" class="rounded border border-slate-700 px-3 py-1">Download</a>
                                    <form method="POST" action="{{ route('platform.migrations.restore', $migration) }}" class="inline">
                                        @csrf
                                        <input type="hidden" name="confirmation" value="RESTORE">
                                        <button class="rounded border border-red-700 px-3 py-1 text-red-200">Confirm restore log</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-6 text-center text-slate-400">No migration packages yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
