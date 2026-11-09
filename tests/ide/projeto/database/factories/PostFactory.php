<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class PostFactory extends Factory
{
    public function definition(): array
    {
        return ['titulo' => fake()->sentence(3), 'corpo' => fake()->paragraph()];
    }
}
