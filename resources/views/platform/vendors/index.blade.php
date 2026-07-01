@extends('platform.layout')

@section('title', 'Vendors')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold">Vendors</h1>
            <p class="mt-2 text-slate-400">Platform-level vendor tenant registry.</p>
        </div>
        <a href="{{ route('platform.vendors.create') }}" class="rounded bg-cyan-400 px-4 py-2 font-semibold text-slate-950">Create vendor</a>
    </div>

    <div class="overflow-hidden rounded border border-slate-800 bg-slate-900">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-950 text-slate-300">
                <tr>
                    <th class="px-4 py-3">Name</th>
                    <th class="px-4 py-3">Slug</th>
                    <th class="px-4 py-3">Domain</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800">
                @forelse ($vendors as $vendor)
                    <tr>
                        <td class="px-4 py-3">{{ $vendor->name }}</td>
                        <td class="px-4 py-3">{{ $vendor->slug }}</td>
                        <td class="px-4 py-3">{{ $vendor->domain ?? '-' }}</td>
                        <td class="px-4 py-3">{{ $vendor->is_active ? 'Active' : 'Suspended' }}</td>
                        <td class="space-x-3 px-4 py-3">
                            <a class="text-cyan-300" href="{{ route('platform.vendors.show', $vendor) }}">Details</a>
                            <a class="text-cyan-300" href="{{ route('platform.vendors.edit', $vendor) }}">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-slate-400">No vendors created yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $vendors->links() }}
    </div>
@endsection
