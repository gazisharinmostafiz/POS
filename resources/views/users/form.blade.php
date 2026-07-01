<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $user->exists ? 'Edit user' : 'Create user' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-100 text-slate-950 antialiased">
    <main class="mx-auto max-w-3xl px-4 py-6">
        <h1 class="mb-6 text-3xl font-black">{{ $user->exists ? 'Edit user' : 'Create user' }}</h1>

        @if ($errors->any())
            <div class="mb-4 rounded bg-red-100 px-4 py-3 font-bold text-red-900">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ $action }}" data-loading-text="Saving..." class="rounded-lg border border-slate-300 bg-white p-4">
            @csrf
            @if ($method !== 'POST') @method($method) @endif

            <div class="grid gap-4">
                <label class="block"><span class="font-bold">Name</span><input name="name" value="{{ old('name', $user->name) }}" class="pos-field mt-1" required></label>
                <label class="block"><span class="font-bold">Email</span><input name="email" type="email" value="{{ old('email', $user->email) }}" class="pos-field mt-1" required></label>
                @if (! $user->exists)
                    <label class="block"><span class="font-bold">Password</span><input name="password" type="password" class="pos-field mt-1" required></label>
                @endif
                @if ($scope['platform'])
                    <label class="block"><span class="font-bold">Tenant</span><select name="tenant_id" class="pos-field mt-1"><option value="">Platform user</option>@foreach($tenants as $tenant)<option value="{{ $tenant->id }}" @selected(old('tenant_id', $user->tenant_id) == $tenant->id)>{{ $tenant->name }}</option>@endforeach</select></label>
                @endif
                <label class="block"><span class="font-bold">Role</span><select name="role" class="pos-field mt-1">@foreach($roles as $role)<option value="{{ $role }}" @selected(old('role', $user->role) === $role)>{{ $role }}</option>@endforeach</select></label>
                <label class="inline-flex items-center gap-2 font-bold"><input name="is_active" type="checkbox" value="1" @checked(old('is_active', $user->is_active ?? true))> Active</label>
            </div>

            <button type="submit" class="mt-5 min-h-11 rounded bg-slate-950 px-4 py-2 font-bold text-white">Save user</button>
        </form>

        @if ($user->exists)
            <form method="POST" data-confirm="Reset this user's password?" data-loading-text="Resetting..." action="{{ $scope['platform'] ? route('platform.users.password', $user) : route('tenant.users.password', $user) }}" class="mt-4 rounded-lg border border-slate-300 bg-white p-4">
                @csrf @method('PATCH')
                <label class="block"><span class="font-bold">Reset password</span><input name="password" type="password" class="pos-field mt-1" required></label>
                <button type="submit" class="mt-3 min-h-11 rounded bg-slate-800 px-4 py-2 font-bold text-white">Reset password</button>
            </form>
        @endif
    </main>
</body>
</html>
