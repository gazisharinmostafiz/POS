<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class MenuItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'category_id' => Category::factory(),
            'name' => $this->faker->words(2, true),
            'description' => $this->faker->sentence(),
            'price' => $this->faker->randomFloat(2, 5, 40),
            'is_available' => true,
            'is_active' => true,
            'sort_order' => 1,
        ];
    }

    public function forTenantCategory(Tenant $tenant, Category $category): self
    {
        return $this->state(fn () => [
            'tenant_id' => $tenant->id,
            'category_id' => $category->id,
        ]);
    }
}
