@php
    $title = 'Printer Settings';
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} - Tong POS Platform</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-950 text-slate-100 antialiased">
    <main class="mx-auto max-w-7xl px-6 py-8">
        <div class="mb-6 flex items-start justify-between gap-4">
            <div>
                <p class="text-sm font-semibold uppercase tracking-widest text-cyan-300">Tenant settings</p>
                <h1 class="mt-2 text-3xl font-bold">Printer settings</h1>
                <p class="mt-2 text-slate-400">Browser printing is supported first. Network, ePOS, CloudPRNT, and local bridge adapters can consume queued jobs later.</p>
            </div>
            <a href="{{ route('areas.tenant-admin') }}" class="rounded border border-slate-700 px-4 py-2 text-sm text-slate-200">Back</a>
        </div>

        @if (session('status'))
            <div class="mb-6 rounded border border-emerald-700 bg-emerald-950 px-4 py-3 text-sm text-emerald-200">
                {{ session('status') }}
            </div>
        @endif

        @error('printer')
            <div class="mb-6 rounded border border-red-700 bg-red-950 px-4 py-3 text-sm text-red-200">
                {{ $message }}
            </div>
        @enderror

        <div class="grid gap-6 lg:grid-cols-[380px_1fr]">
            <form method="POST" action="{{ route('tenant.printers.store') }}" class="rounded border border-slate-800 bg-slate-900 p-5">
                @csrf
                <h2 class="text-xl font-semibold">Add printer</h2>

                <div class="mt-4 space-y-4">
                    <div>
                        <label for="name" class="block text-sm font-medium">Name</label>
                        <input id="name" name="name" required value="{{ old('name') }}" class="mt-2 w-full rounded border border-slate-700 bg-slate-950 px-3 py-2">
                        @error('name') <p class="mt-1 text-sm text-red-300">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="type" class="block text-sm font-medium">Type</label>
                            <select id="type" name="type" class="mt-2 w-full rounded border border-slate-700 bg-slate-950 px-3 py-2">
                                @foreach ($types as $type)
                                    <option value="{{ $type }}">{{ $type }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="role" class="block text-sm font-medium">Role</label>
                            <select id="role" name="role" class="mt-2 w-full rounded border border-slate-700 bg-slate-950 px-3 py-2">
                                @foreach ($roles as $role)
                                    <option value="{{ $role }}">{{ $role }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="ip_address" class="block text-sm font-medium">IP address</label>
                            <input id="ip_address" name="ip_address" value="{{ old('ip_address') }}" class="mt-2 w-full rounded border border-slate-700 bg-slate-950 px-3 py-2">
                        </div>
                        <div>
                            <label for="port" class="block text-sm font-medium">Port</label>
                            <input id="port" name="port" type="number" min="1" max="65535" value="{{ old('port') }}" class="mt-2 w-full rounded border border-slate-700 bg-slate-950 px-3 py-2">
                        </div>
                    </div>

                    <div>
                        <label for="paper_size" class="block text-sm font-medium">Paper size</label>
                        <select id="paper_size" name="paper_size" class="mt-2 w-full rounded border border-slate-700 bg-slate-950 px-3 py-2">
                            <option value="80mm">80mm</option>
                            <option value="58mm">58mm</option>
                            <option value="A4">A4</option>
                        </select>
                    </div>

                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="is_active" value="1" checked class="rounded border-slate-700 bg-slate-950">
                        Active
                    </label>
                </div>

                <button type="submit" class="mt-6 rounded bg-cyan-400 px-4 py-2 font-semibold text-slate-950">Save printer</button>
            </form>

            <section class="space-y-6">
                <div class="rounded border border-slate-800 bg-slate-900 p-5">
                    <h2 class="text-xl font-semibold">Configured printers</h2>
                    <div class="mt-4 overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="text-slate-400">
                                <tr>
                                    <th class="py-2">Name</th>
                                    <th>Type</th>
                                    <th>Role</th>
                                    <th>Connection</th>
                                    <th>Status</th>
                                    <th class="text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800">
                                @forelse ($printers as $printer)
                                    <tr>
                                        <td class="py-3 font-medium">{{ $printer->name }}</td>
                                        <td>{{ $printer->type }}</td>
                                        <td>{{ $printer->role }}</td>
                                        <td>{{ $printer->ip_address ?: 'Browser' }}{{ $printer->port ? ':'.$printer->port : '' }} / {{ $printer->paper_size }}</td>
                                        <td>{{ $printer->is_active ? 'Active' : 'Inactive' }}</td>
                                        <td class="text-right">
                                            <form method="POST" action="{{ route('tenant.printers.test', $printer) }}" class="inline">
                                                @csrf
                                                <button class="rounded border border-slate-700 px-3 py-1 text-sm">Test print</button>
                                            </form>
                                            @if (in_array($printer->type, ['network', 'epson_epos'], true))
                                                <form method="POST" action="{{ route('tenant.printers.connection-test', $printer) }}" class="ml-2 inline">
                                                    @csrf
                                                    <button class="rounded border border-cyan-700 px-3 py-1 text-sm text-cyan-200">Connection</button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="py-6 text-center text-slate-400">No printers configured.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="rounded border border-slate-800 bg-slate-900 p-5">
                    <h2 class="text-xl font-semibold">Recent print jobs</h2>
                    <div class="mt-4 space-y-3">
                        @forelse ($jobs as $job)
                            <div class="flex items-center justify-between gap-4 rounded border border-slate-800 bg-slate-950 px-4 py-3 text-sm">
                                <div>
                                    <p class="font-semibold">{{ $job->type }} / {{ $job->status }}</p>
                                    <p class="text-slate-400">{{ $job->printer?->name ?? 'No printer' }} - attempts {{ $job->attempts }}</p>
                                    @if ($job->last_error)
                                        <p class="mt-1 text-red-300">{{ $job->last_error }}</p>
                                    @endif
                                </div>
                                @if ($job->status === 'failed')
                                    <form method="POST" action="{{ route('tenant.printers.jobs.retry', $job) }}">
                                        @csrf
                                        <button class="rounded border border-cyan-700 px-3 py-1 text-cyan-200">Retry</button>
                                    </form>
                                @endif
                            </div>
                        @empty
                            <p class="text-sm text-slate-400">No print jobs yet.</p>
                        @endforelse
                    </div>
                </div>
            </section>
        </div>
    </main>
</body>
</html>
