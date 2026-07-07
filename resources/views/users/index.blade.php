@extends(request()->routeIs('platform.*') ? 'platform.layout' : 'layouts.tenant-app', request()->routeIs('platform.*') ? [] : [
    'pageTitle' => 'Team',
    'activeNav' => 'users',
    'bodyClass' => 'pos-theme-light',
])

@if (request()->routeIs('platform.*'))
    @section('title', 'User Management')
@endif

@section('content')
<div class="pos-page">
    @include('partials.admin-page-header', [
        'eyebrow' => 'Administration',
        'title' => 'Team members',
        'subtitle' => 'Manage staff accounts, roles, and access.',
        'showHome' => ! request()->routeIs('platform.*'),
        'actions' => view('partials.admin-header-action', [
            'href' => $scope['platform'] ? ($scope['tenant'] ? route('platform.vendors.users.create', $scope['tenant']) : route('platform.users.create')) : route('tenant.users.create'),
            'label' => 'Add user',
        ])->render(),
    ])

    @include('partials.admin-flash')

    @if ($users->isEmpty())
        <section class="pos-empty">No users found for this scope.</section>
    @else
        <div class="pos-table-wrap">
            <table class="pos-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        @if ($scope['platform'])<th>Tenant</th>@endif
                        <th>Role</th>
                        <th>Status</th>
                        <th>Last login</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($users as $user)
                        <tr>
                            <td class="font-semibold">{{ $user->name }}</td>
                            <td>{{ $user->email }}</td>
                            @if ($scope['platform'])<td>{{ $user->tenant?->name ?: 'Platform' }}</td>@endif
                            <td><span class="pos-badge-neutral capitalize">{{ str_replace('_', ' ', $user->role) }}</span></td>
                            <td><span class="{{ $user->is_active ? 'pos-badge-success' : 'pos-badge-warning' }}">{{ $user->is_active ? 'Active' : 'Inactive' }}</span></td>
                            <td class="text-slate-500">{{ $user->last_login_at?->format('M j, H:i') ?: 'Never' }}</td>
                            <td>
                                <div class="flex flex-wrap justify-end gap-2">
                                    <a href="{{ $scope['platform'] ? route('platform.users.edit', $user) : route('tenant.users.edit', $user) }}" class="pos-btn-secondary px-3 py-1.5 text-xs">Edit</a>
                                    <form method="POST" data-confirm="{{ $user->is_active ? 'Deactivate this user?' : 'Reactivate this user?' }}" data-loading-text="Saving…" action="{{ $scope['platform'] ? ($user->is_active ? route('platform.users.deactivate', $user) : route('platform.users.activate', $user)) : ($user->is_active ? route('tenant.users.deactivate', $user) : route('tenant.users.activate', $user)) }}">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="pos-btn-secondary px-3 py-1.5 text-xs">{{ $user->is_active ? 'Deactivate' : 'Activate' }}</button>
                                    </form>
                                    @if (auth()->user()->hasRole([\App\Support\Roles::SUPER_ADMIN, \App\Support\Roles::PLATFORM_OWNER]))
                                        <form method="POST" data-confirm="Delete this user permanently?" data-loading-text="Deleting…" action="{{ $scope['platform'] ? route('platform.users.destroy', $user) : route('tenant.users.destroy', $user) }}">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="pos-btn-danger px-3 py-1.5 text-xs">Delete</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
