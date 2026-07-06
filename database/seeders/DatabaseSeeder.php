<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(SubscriptionPlanSeeder::class);
        $this->call(DemoTenantSeeder::class);
        $this->call(DemoUserSeeder::class);
        $this->call(DemoDataSeeder::class);
    }
}
