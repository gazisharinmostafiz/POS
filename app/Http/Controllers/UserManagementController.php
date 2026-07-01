<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\User;
use App\Services\AuditLogService;
use App\Support\Roles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    public function index(Request $request): View
    {
        $scope = $this->scope($request, $request->route('tenant'));

        return view('users.index', [
            'users' => $this->scopedUsers($scope)->with('tenant')->orderBy('name')->get(),
            'scope' => $scope,
            'roles' => $this->rolesForScope($scope),
            'tenants' => $scope['platform'] ? Tenant::query()->orderBy('name')->get() : collect(),
        ]);
    }

    public function create(Request $request): View
    {
        $scope = $this->scope($request, $request->route('tenant'));

        return view('users.form', [
            'user' => new User(['tenant_id' => $scope['tenant']?->id, 'is_active' => true]),
            'scope' => $scope,
            'roles' => $this->rolesForScope($scope),
            'tenants' => $scope['platform'] ? Tenant::query()->orderBy('name')->get() : collect(),
            'action' => $scope['create_route'],
            'method' => 'POST',
        ]);
    }

    public function store(Request $request, AuditLogService $auditLog): RedirectResponse
    {
        $scope = $this->scope($request, $request->route('tenant'));
        $payload = $this->validatedPayload($request, $scope, true);

        $user = User::query()->create($payload);
        $auditLog->userChanged(AuditLogService::USER_CREATED, $user, ['attributes' => $this->auditAttributes($user)]);

        return redirect($scope['index_url'])->with('status', 'User created.');
    }

    public function edit(Request $request, User $user): View
    {
        $scope = $this->scopeForUser($request, $user);
        $this->authorizeScopedUser($scope, $user);

        return view('users.form', [
            'user' => $user,
            'scope' => $scope,
            'roles' => $this->rolesForScope($scope),
            'tenants' => $scope['platform'] ? Tenant::query()->orderBy('name')->get() : collect(),
            'action' => $scope['platform'] ? route('platform.users.update', $user) : route('tenant.users.update', $user),
            'method' => 'PUT',
        ]);
    }

    public function update(Request $request, User $user, AuditLogService $auditLog): RedirectResponse
    {
        $scope = $this->scopeForUser($request, $user);
        $this->authorizeScopedUser($scope, $user);
        $before = $this->auditAttributes($user);
        $payload = $this->validatedPayload($request, $scope, false, $user);

        $user->fill($payload)->save();
        $auditLog->userChanged(AuditLogService::USER_UPDATED, $user, [
            'before' => $before,
            'after' => $this->auditAttributes($user->fresh()),
        ]);

        return redirect($scope['index_url'])->with('status', 'User updated.');
    }

    public function activate(Request $request, User $user, AuditLogService $auditLog): RedirectResponse
    {
        $scope = $this->scopeForUser($request, $user);
        $this->authorizeScopedUser($scope, $user);
        $user->forceFill(['is_active' => true])->save();
        $auditLog->userChanged(AuditLogService::USER_ACTIVATED, $user);

        return back()->with('status', 'User activated.');
    }

    public function deactivate(Request $request, User $user, AuditLogService $auditLog): RedirectResponse
    {
        $scope = $this->scopeForUser($request, $user);
        $this->authorizeScopedUser($scope, $user);
        $user->forceFill(['is_active' => false])->save();
        $auditLog->userChanged(AuditLogService::USER_DEACTIVATED, $user);

        return back()->with('status', 'User deactivated.');
    }

    public function resetPassword(Request $request, User $user, AuditLogService $auditLog): RedirectResponse
    {
        $scope = $this->scopeForUser($request, $user);
        $this->authorizeScopedUser($scope, $user);
        $payload = $request->validate(['password' => ['required', 'string', 'min:8']]);
        $user->forceFill(['password' => $payload['password']])->save();
        $auditLog->userChanged(AuditLogService::USER_PASSWORD_RESET, $user);

        return back()->with('status', 'Password reset.');
    }

    public function destroy(Request $request, User $user, AuditLogService $auditLog): RedirectResponse
    {
        $scope = $this->scopeForUser($request, $user);
        $this->authorizeScopedUser($scope, $user);

        abort_unless($request->user()->hasRole([Roles::SUPER_ADMIN, Roles::PLATFORM_OWNER]), 403);
        abort_if($this->isOnlyAdminDeletingSelf($request->user(), $user), 422, 'Cannot delete the only admin account.');

        $auditLog->deleted($user, ['attributes' => $this->auditAttributes($user)]);
        $user->delete();

        return redirect($scope['index_url'])->with('status', 'User deleted.');
    }

    private function validatedPayload(Request $request, array $scope, bool $creating, ?User $user = null): array
    {
        $roles = $this->rolesForScope($scope);
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user)],
            'role' => ['required', Rule::in($roles)],
            'is_active' => ['nullable', 'boolean'],
        ];

        if ($scope['platform']) {
            $rules['tenant_id'] = ['nullable', 'exists:tenants,id'];
        }

        if ($creating) {
            $rules['password'] = ['required', 'string', 'min:8'];
        }

        $payload = $request->validate($rules);
        $payload['is_active'] = $request->boolean('is_active', true);
        $payload['tenant_id'] = match (true) {
            ! $scope['platform'] => $scope['tenant']->id,
            $scope['tenant'] !== null => $scope['tenant']->id,
            default => $payload['tenant_id'] ?? null,
        };

        if (! $creating) {
            unset($payload['password']);
        }

        return $payload;
    }

    private function scope(Request $request, mixed $tenant): array
    {
        $platform = $request->user()->hasRole(Roles::PLATFORM_OWNER);
        $tenant = $platform ? $this->resolveTenant($tenant) : current_tenant();

        return [
            'platform' => $platform,
            'tenant' => $tenant,
            'index_url' => $platform
                ? ($tenant ? route('platform.vendors.users.index', $tenant) : route('platform.users.index'))
                : route('tenant.users.index'),
            'create_route' => $platform
                ? ($tenant ? route('platform.vendors.users.store', $tenant) : route('platform.users.store'))
                : route('tenant.users.store'),
        ];
    }

    private function resolveTenant(mixed $tenant): ?Tenant
    {
        if ($tenant instanceof Tenant) {
            return $tenant;
        }

        if ($tenant) {
            return Tenant::query()->findOrFail($tenant);
        }

        return null;
    }

    private function scopeForUser(Request $request, User $user): array
    {
        return $this->scope($request, $request->user()->hasRole(Roles::PLATFORM_OWNER) ? $user->tenant : null);
    }

    private function scopedUsers(array $scope)
    {
        $query = User::query();

        if ($scope['platform'] && ! $scope['tenant']) {
            return $query;
        }

        return $query->where('tenant_id', $scope['tenant']->id);
    }

    private function authorizeScopedUser(array $scope, User $user): void
    {
        if ($scope['platform']) {
            return;
        }

        abort_unless((int) $user->tenant_id === (int) $scope['tenant']->id, 404);
    }

    private function rolesForScope(array $scope): array
    {
        return $scope['platform']
            ? Roles::ALL
            : [Roles::SUPER_ADMIN, Roles::ADMIN, Roles::WAITER, Roles::COUNTER, Roles::KITCHEN];
    }

    private function isOnlyAdminDeletingSelf(User $actor, User $target): bool
    {
        if ($actor->id !== $target->id || ! $target->hasRole([Roles::PLATFORM_OWNER, Roles::SUPER_ADMIN, Roles::ADMIN])) {
            return false;
        }

        $query = User::query()->whereIn('role', [Roles::PLATFORM_OWNER, Roles::SUPER_ADMIN, Roles::ADMIN]);

        $target->tenant_id === null
            ? $query->whereNull('tenant_id')
            : $query->where('tenant_id', $target->tenant_id);

        return $query->count() <= 1;
    }

    private function auditAttributes(User $user): array
    {
        return $user->only(['id', 'tenant_id', 'name', 'email', 'role', 'is_active']);
    }
}
