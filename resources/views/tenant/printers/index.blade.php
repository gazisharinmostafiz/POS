@extends('layouts.tenant-app', [
    'pageTitle' => 'Printers',
    'activeNav' => 'printers',
    'bodyClass' => 'pos-theme-light',
])

@section('content')
<div class="pos-page">
    @include('partials.admin-page-header', [
        'eyebrow' => 'Configuration',
        'title' => 'Printer settings',
        'subtitle' => 'Configure receipt and kitchen printers for your venue.',
    ])

    @include('partials.admin-flash')

    @error('printer')
        <div class="pos-alert-error mb-6">{{ $message }}</div>
    @enderror

    <div class="grid gap-6 xl:grid-cols-[22rem_1fr]">
        <form method="POST" action="{{ route('tenant.printers.store') }}" class="pos-card p-6">
            @csrf
            <h2 class="font-bold text-slate-900">Add printer</h2>
            <div class="mt-4 space-y-4">
                <label class="block">
                    <span class="pos-label">Name</span>
                    <input id="name" name="name" required value="{{ old('name') }}" class="pos-field mt-1.5">
                    @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </label>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="block">
                        <span class="pos-label">Type</span>
                        <select id="type" name="type" class="pos-field mt-1.5">
                            @foreach ($types as $type)
                                <option value="{{ $type }}">{{ $type }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block">
                        <span class="pos-label">Role</span>
                        <select id="role" name="role" class="pos-field mt-1.5">
                            @foreach ($roles as $role)
                                <option value="{{ $role }}">{{ $role }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="block">
                        <span class="pos-label">IP address</span>
                        <input id="ip_address" name="ip_address" value="{{ old('ip_address') }}" class="pos-field mt-1.5">
                    </label>
                    <label class="block">
                        <span class="pos-label">Port</span>
                        <input id="port" name="port" type="number" min="1" max="65535" value="{{ old('port') }}" class="pos-field mt-1.5">
                    </label>
                </div>
                <label class="block">
                    <span class="pos-label">Paper size</span>
                    <select id="paper_size" name="paper_size" class="pos-field mt-1.5">
                        <option value="80mm">80mm</option>
                        <option value="58mm">58mm</option>
                        <option value="A4">A4</option>
                    </select>
                </label>
                <div class="pos-toggle-row">
                    <div><p class="pos-toggle-label">Active</p></div>
                    <input type="checkbox" name="is_active" value="1" checked class="h-5 w-5 rounded border-slate-300 text-brand-600">
                </div>
            </div>
            <button type="submit" class="pos-btn-primary mt-6 w-full">Save printer</button>
        </form>

        <div class="space-y-6">
            <section class="pos-card overflow-hidden">
                <div class="pos-card-header">
                    <h2 class="font-bold">Configured printers</h2>
                </div>
                <div class="pos-table-wrap border-0">
                    <table class="pos-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Role</th>
                                <th>Connection</th>
                                <th>Status</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($printers as $printer)
                                <tr>
                                    <td class="font-semibold">{{ $printer->name }}</td>
                                    <td>{{ $printer->type }}</td>
                                    <td>{{ $printer->role }}</td>
                                    <td class="text-sm text-slate-500">{{ $printer->ip_address ?: 'Browser' }}{{ $printer->port ? ':'.$printer->port : '' }} · {{ $printer->paper_size }}</td>
                                    <td><span class="{{ $printer->is_active ? 'pos-badge-success' : 'pos-badge-neutral' }}">{{ $printer->is_active ? 'Active' : 'Inactive' }}</span></td>
                                    <td>
                                        <div class="flex justify-end gap-2">
                                            <form method="POST" action="{{ route('tenant.printers.test', $printer) }}">
                                                @csrf
                                                <button class="pos-btn-secondary px-3 py-1.5 text-xs">Test</button>
                                            </form>
                                            @if (in_array($printer->type, ['network', 'epson_epos'], true))
                                                <form method="POST" action="{{ route('tenant.printers.connection-test', $printer) }}">
                                                    @csrf
                                                    <button class="pos-btn-secondary px-3 py-1.5 text-xs">Ping</button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="py-8 text-center text-slate-500">No printers configured.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="pos-card p-6">
                <h2 class="font-bold">Recent print jobs</h2>
                <div class="mt-4 space-y-3">
                    @forelse ($jobs as $job)
                        <div class="flex items-center justify-between gap-4 rounded-xl border border-slate-200 bg-surface-subtle px-4 py-3 text-sm">
                            <div>
                                <p class="font-semibold">{{ $job->type }} · {{ $job->status }}</p>
                                <p class="text-slate-500">{{ $job->printer?->name ?? 'No printer' }} · {{ $job->attempts }} attempt(s)</p>
                                @if ($job->last_error)
                                    <p class="mt-1 text-red-600">{{ $job->last_error }}</p>
                                @endif
                            </div>
                            @if ($job->status === 'failed')
                                <form method="POST" action="{{ route('tenant.printers.jobs.retry', $job) }}">
                                    @csrf
                                    <button class="pos-btn-secondary px-3 py-1.5 text-xs">Retry</button>
                                </form>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">No print jobs yet.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</div>
@endsection
