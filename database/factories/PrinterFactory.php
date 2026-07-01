<?php

namespace Database\Factories;

use App\Models\Printer;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class PrinterFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => $this->faker->words(2, true).' Printer',
            'type' => Printer::TYPE_BROWSER,
            'role' => Printer::ROLE_RECEIPT,
            'paper_size' => '80mm',
            'is_active' => true,
        ];
    }

    public function receipt(): self
    {
        return $this->state(fn () => ['role' => Printer::ROLE_RECEIPT]);
    }

    public function kitchen(): self
    {
        return $this->state(fn () => ['role' => Printer::ROLE_KITCHEN]);
    }
}
