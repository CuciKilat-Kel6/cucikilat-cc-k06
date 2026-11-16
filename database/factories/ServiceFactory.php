<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => fake()->unique()->words(3, true), 'type' => 'reguler', 'price' => 7000, 'description' => 'Cuci reguler'];
    }

    public function express(): static
    {
        return $this->state(fn () => ['type' => 'express']);
    }

    public function satuan(): static
    {
        return $this->state(fn () => ['type' => 'satuan', 'price' => 30000]);
    }
}
