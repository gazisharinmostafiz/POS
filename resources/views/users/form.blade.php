@extends(request()->routeIs('platform.*') ? 'platform.layout' : 'layouts.tenant-app', request()->routeIs('platform.*') ? [] : [
    'pageTitle' => $user->exists ? 'Edit User' : 'Create User',
    'activeNav' => 'users',
    'bodyClass' => 'pos-theme-light',
])

@if (request()->routeIs('platform.*'))
    @section('title', $user->exists ? 'Edit User' : 'Create User')
@endif

@section('content')
<div class="pos-page max-w-2xl">
    @include('partials.admin-page-header', [
        'eyebrow' => 'Administration',
        'title' => $user->exists ? 'Edit team member' : 'Add team member',
        'showHome' => ! request()->routeIs('platform.*'),
        'backUrl' => $scope['platform'] ? ($scope['tenant'] ? route('platform.vendors.users.index', $scope['tenant']) : route('platform.users.index')) : route('tenant.users.index'),
        'backLabel' => 'All users',
    ])

    @include('partials.admin-flash')

    <form method="POST" action="{{ $action }}" data-loading-text="Saving…" class="pos-card p-6">
        @csrf
        @if ($method !== 'POST') @method($method) @endif

        <div class="grid gap-4">
            <label class="block">
                <span class="pos-label">Full name</span>
                <input name="name" value="{{ old('name', $user->name) }}" class="pos-field mt-1.5" required>
            </label>
            <label class="block">
                <span class="pos-label">Email</span>
                <input name="email" type="email" value="{{ old('email', $user->email) }}" class="pos-field mt-1.5" required>
            </label>
            @if (! $user->exists)
                <label class="block">
                    <span class="pos-label">Password</span>
                    <input name="password" type="password" class="pos-field mt-1.5" required>
                </label>
            @endif
            @if ($scope['platform'])
                <label class="block">
                    <span class="pos-label">Tenant</span>
                    <select name="tenant_id" class="pos-field mt-1.5">
                        <option value="">Platform user</option>
                        @foreach ($tenants as $tenant)
                            <option value="{{ $tenant->id }}" @selected(old('tenant_id', $user->tenant_id) == $tenant->id)>{{ $tenant->name }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
            <label class="block">
                <span class="pos-label">Role</span>
                <select name="role" class="pos-field mt-1.5">
                    @foreach ($roles as $role)
                        <option value="{{ $role }}" @selected(old('role', $user->role) === $role)>{{ str_replace('_', ' ', ucfirst($role)) }}</option>
                    @endforeach
                </select>
            </label>
            <div class="pos-toggle-row">
                <div><p class="pos-toggle-label">Account active</p></div>
                <input type="hidden" name="is_active" value="0">
                <input name="is_active" type="checkbox" value="1" @checked(old('is_active', $user->is_active ?? true)) class="h-5 w-5 rounded border-slate-300 text-brand-600">
            </div>
        </div>

        <button type="submit" class="pos-btn-primary mt-6">Save user</button>
    </form>

    @if ($user->exists)
        <form method="POST" data-confirm="Reset this user's password?" data-loading-text="Resetting…" action="{{ $scope['platform'] ? route('platform.users.password', $user) : route('tenant.users.password', $user) }}" class="pos-card mt-6 p-6">
            @csrf @method('PATCH')
            <h2 class="font-bold text-slate-900">Reset password</h2>
            <label class="mt-4 block">
                <span class="pos-label">New password</span>
                <input name="password" type="password" class="pos-field mt-1.5" required>
            </label>
            <button type="submit" class="pos-btn-dark mt-4">Reset password</button>
        </form>
    @endif
</div>
@endsection
