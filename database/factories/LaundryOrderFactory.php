<?php

namespace Database\Factories;

use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class LaundryOrderFactory extends Factory
{
    public function definition(): array
    {
        return ['service_id' => Service::factory(), 'code' => 'CK-'.strtoupper(Str::random(12)), 'customer_name' => fake()->name(), 'phone' => '081234567890', 'service_name' => 'Cuci Reguler', 'service_type' => 'reguler', 'unit_price' => 7000, 'weight_grams' => 2000, 'billable_grams' => 2000, 'total' => 14000, 'status' => 'DITERIMA', 'due_at' => now()->addDays(3), 'history' => [['status' => 'DITERIMA', 'at' => now()->toIso8601String()]]];
    }
}
