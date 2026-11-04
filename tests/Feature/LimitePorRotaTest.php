<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** throttle:N,M conta cada rota separado (antes, todas dividiam um contador por pessoa). */
class LimitePorRotaTest extends TestCase
{
    use RefreshDatabase;

    public function test_cada_rota_tem_o_proprio_contador(): void
    {
        Route::middleware(['web', 'throttle:2,1'])->get('/_teste/a', fn () => 'a')->name('teste.a');
        Route::middleware(['web', 'throttle:2,1'])->get('/_teste/b', fn () => 'b')->name('teste.b');
        $u = User::factory()->create();

        $this->actingAs($u)->get('/_teste/a')->assertOk();
        $this->actingAs($u)->get('/_teste/a')->assertOk();
        $this->actingAs($u)->get('/_teste/a')->assertStatus(429);
        // a outra rota não foi gasta pela primeira
        $this->actingAs($u)->get('/_teste/b')->assertOk();
        $this->actingAs($u)->get('/_teste/b')->assertOk();
        $this->actingAs($u)->get('/_teste/b')->assertStatus(429);
    }
}
