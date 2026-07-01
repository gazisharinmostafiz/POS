<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>User Management</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-100 text-slate-950 antialiased">
    <main class="mx-auto max-w-7xl px-4 py-6">
        <header class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-sm font-bold uppercase tracking-widest text-slate-500">Administration</p>
                <h1 class="text-3xl font-black">User management</h1>
            </div>
            <a class="rounded bg-slate-950 px-4 py-3 font-bold text-white" href="{{ $scope['platform'] ? ($scope['tenant'] ? route('platform.vendors.users.create', $scope['tenant']) : route('platform.users.create')) : route('tenant.users.create') }}">Create user</a>
        </header>

        @if (session('status'))
            <div class="mb-4 rounded bg-emerald-100 px-4 py-3 font-bold text-emerald-900">{{ session('status') }}</div>
        @endif

        @if ($users->isEmpty())
            <section class="pos-empty">No users found for this scope.</section>
        @else
        <section class="overflow-x-auto rounded-lg border border-slate-300 bg-white">
            <table class="w-full min-w-[920px] text-left text-sm">
                <thead class="border-b bg-slate-50 text-slate-500">
                    <tr><th class="p-3">Name</th><th>Email</th><th>Tenant</th><th>Role</th><th>Status</th><th>Last login</th><th class="text-right">Actions</th></tr>
                </thead>
                <tbody>
                    @foreach ($users as $user)
                        <tr class="border-b">
                            <td class="p-3 font-bold">{{ $user->name }}</td>
                            <td>{{ $user->email }}</td>
                            <td>{{ $user->tenant?->name ?: 'Platform' }}</td>
                            <td>{{ $user->role }}</td>
                            <td>{{ $user->is_active ? 'Active' : 'Inactive' }}</td>
                            <td>{{ $user->last_login_at?->format('Y-m-d H:i') ?: 'Never' }}</td>
                            <td class="p-3">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <a class="rounded bg-slate-100 px-3 py-2 font-bold" href="{{ $scope['platform'] ? route('platform.users.edit', $user) : route('tenant.users.edit', $user) }}">Edit</a>
                                    <form method="POST" data-confirm="{{ $user->is_active ? 'Deactivate this user?' : 'Reactivate this user?' }}" data-loading-text="Saving..." action="{{ $scope['platform'] ? ($user->is_active ? route('platform.users.deactivate', $user) : route('platform.users.activate', $user)) : ($user->is_active ? route('tenant.users.deactivate', $user) : route('tenant.users.activate', $user)) }}">@csrf @method('PATCH')<button class="rounded bg-slate-100 px-3 py-2 font-bold" type="submit">{{ $user->is_active ? 'Deactivate' : 'Activate' }}</button></form>
                                    @if (auth()->user()->hasRole([\App\Support\Roles::SUPER_ADMIN, \App\Support\Roles::PLATFORM_OWNER]))
                                        <form method="POST" data-confirm="Delete this user? This action is restricted and cannot be undone." data-loading-text="Deleting..." action="{{ $scope['platform'] ? route('platform.users.destroy', $user) : route('tenant.users.destroy', $user) }}">@csrf @method('DELETE')<button class="rounded bg-red-100 px-3 py-2 font-bold text-red-800" type="submit">Delete</button></form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
        @endif
    </main>
</body>
</html>
