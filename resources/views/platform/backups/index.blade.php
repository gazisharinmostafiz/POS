@extends('platform.layout')

@section('content')
    <div class="mb-6 flex items-start justify-between gap-4">
        <div>
            <p class="text-sm font-semibold uppercase tracking-widest text-cyan-300">Platform owner</p>
            <h1 class="mt-2 text-3xl font-bold">Platform backups</h1>
            <p class="mt-2 text-slate-400">Create private full-platform backups and database snapshots.</p>
        </div>
    </div>

    @if (session('status'))
        <div class="mb-6 rounded border border-emerald-700 bg-emerald-950 px-4 py-3 text-sm text-emerald-200">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('platform.backups.store') }}" class="mb-6 rounded border border-slate-800 bg-slate-900 p-5">
        @csrf
        <div class="grid gap-4 md:grid-cols-3">
            <div>
                <label class="block text-sm font-medium" for="type">Backup type</label>
                <select id="type" name="type" class="mt-2 w-full rounded border border-slate-700 bg-slate-950 px-3 py-2">
                    <option value="database">Database</option>
                    <option value="full_platform">Full platform</option>
                </select>
            </div>
            <label class="mt-8 flex items-center gap-2 text-sm">
                <input type="checkbox" name="encrypted" value="1" class="rounded border-slate-700 bg-slate-950">
                Encrypt backup
            </label>
            <div class="mt-7">
                <button class="rounded bg-cyan-400 px-4 py-2 font-semibold text-slate-950">Create backup</button>
            </div>
        </div>
    </form>

    <section class="rounded border border-slate-800 bg-slate-900 p-5">
        <h2 class="text-xl font-semibold">Backup log</h2>
        <div class="mt-4 overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="text-slate-400">
                    <tr>
                        <th class="py-2">Type</th>
                        <th>Status</th>
                        <th>Checksum</th>
                        <th>Size</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse ($backups as $backup)
                        <tr>
                            <td class="py-3">{{ $backup->type }}</td>
                            <td>{{ $backup->status }}</td>
                            <td class="font-mono text-xs">{{ $backup->checksum ?: '-' }}</td>
                            <td>{{ $backup->size_bytes }}</td>
                            <td class="space-x-2 text-right">
                                @if ($backup->status === 'completed')
                                    <a class="rounded border border-slate-700 px-3 py-1" href="{{ route('platform.backups.download', $backup) }}">Download</a>
                                    <form method="POST" action="{{ route('platform.backups.restore', $backup) }}" class="inline">
                                        @csrf
                                        <button class="rounded border border-slate-700 px-3 py-1">Log restore</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-6 text-center text-slate-400">No backups yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
