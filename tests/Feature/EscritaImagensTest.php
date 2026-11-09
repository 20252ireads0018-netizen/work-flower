<?php

namespace Tests\Feature;

use App\Models\DiversoDado;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Cartão "Texto" do Diverso: imagens no meio do texto (img.<cartão>.t<id>) e modo foco. */
class EscritaImagensTest extends TestCase
{
    use RefreshDatabase;

    private const JPG = 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/wAALCAABAAEBAREA/8QAFAABAAAAAAAAAAAAAAAAAAAACf/EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEAAD8AKp//2Q==';

    public function test_pagina_traz_o_editor_com_imagens_e_modo_foco(): void
    {
        $u = User::factory()->create();

        $this->actingAs($u)->get('/areas/diverso')->assertOk()
            ->assertSee('WFEscrita(b, $data)', false)
            ->assertSee('window.WFEscrita.versao = 4', false)
            ->assertSee('wf-es-arq', false)
            ->assertSee('soltarEm($event, i)', false)
            ->assertSee('alternarFoco()', false);
    }

    public function test_imagem_do_texto_e_gravada_lida_com_a_pagina_e_apagada(): void
    {
        $u = User::factory()->create();
        $chave = 'img.bmw99j9oh3sc5.tmw99jh58d8ng';

        $this->actingAs($u)->putJson('/diverso/dados/' . $chave, ['valor' => json_encode(self::JPG)])->assertOk();
        $this->assertSame(json_encode(self::JPG), DiversoDado::where('user_id', $u->id)->where('chave', $chave)->value('valor'));

        // a página entrega a imagem junto com os outros dados do quadro
        $this->actingAs($u)->get('/areas/diverso')->assertOk()->assertSee($chave, false);

        // imagem removida do texto: o editor apaga a chave
        $this->actingAs($u)->deleteJson('/diverso/dados/' . $chave)->assertOk();
        $this->assertFalse(DiversoDado::where('user_id', $u->id)->where('chave', $chave)->exists());
    }
}
