<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /** Valores de exemplo: ajuste nomes, preços e recursos aos seus planos reais. */
    public function run(): void
    {
        $plans = [
            ['name' => 'Básico', 'slug' => 'basico', 'price' => 49.90, 'sort' => 1, 'features' => ['1 usuário', 'Suporte por e-mail']],
            ['name' => 'Pro', 'slug' => 'pro', 'price' => 99.90, 'sort' => 2, 'features' => ['5 usuários', 'Relatórios', 'Suporte prioritário']],
            ['name' => 'Premium', 'slug' => 'premium', 'price' => 199.90, 'sort' => 3, 'features' => ['Usuários ilimitados', 'Relatórios', 'Suporte dedicado']],
        ];

        foreach ($plans as $data) {
            Plan::updateOrCreate(['slug' => $data['slug']], $data + ['active' => true]);
        }
    }
}

