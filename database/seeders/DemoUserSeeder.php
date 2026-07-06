<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Database\Seeder;

class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->where('slug', 'poslab')->firstOrFail();
        $branch = $tenant->branches()->where('slug', 'main')->first();
        $password = 'password';

        $users = [
            ['name' => 'Platform Owner', 'email' => 'platform@poslab.test', 'role' => Roles::PLATFORM_OWNER, 'tenant_id' => null, 'branch_id' => null],
            ['name' => 'Admin', 'email' => 'admin@poslab.test', 'role' => Roles::ADMIN, 'tenant_id' => $tenant->id, 'branch_id' => $branch?->id],
            ['name' => 'Waiter', 'email' => 'waiter@poslab.test', 'role' => Roles::WAITER, 'tenant_id' => $tenant->id, 'branch_id' => $branch?->id],
            ['name' => 'Counter', 'email' => 'counter@poslab.test', 'role' => Roles::COUNTER, 'tenant_id' => $tenant->id, 'branch_id' => $branch?->id],
            ['name' => 'Kitchen', 'email' => 'kitchen@poslab.test', 'role' => Roles::KITCHEN, 'tenant_id' => $tenant->id, 'branch_id' => $branch?->id],
        ];

        foreach ($users as $user) {
            User::query()->updateOrCreate(
                ['email' => $user['email']],
                array_merge($user, [
                    'password' => $password,
                    'is_active' => true,
                ])
            );
        }
    }
}
