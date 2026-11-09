<?php

namespace Database\Seeders;

use App\Models\Post;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Post::factory(3)->create()->each(fn ($p) => $p->comentarios()->create(['texto' => 'Primeiro!']));
    }
}
