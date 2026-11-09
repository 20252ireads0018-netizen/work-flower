<?php

namespace Tests\Feature;

use App\Http\Middleware\FiltroAtaques;
use App\Models\DiversoDado;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** WAF das rotas que gravam e validação do JSON salvo pelos quadros. */
class FiltroAtaquesTest extends TestCase
{
    use RefreshDatabase;

    public function test_assinaturas_de_ataque_sao_reconhecidas(): void
    {
        $ataques = [
            '<script>alert(1)</script>', '<img src=x onerror=alert(1)>', '<svg/onload=alert(1)>', '<iframe src="//x">',
            'javascript:alert(1)', '<a href="javascript:alert(1)">x</a>',
            "' OR '1'='1", "admin' --", '1 UNION SELECT password FROM users', "x' ; DROP TABLE users",
            "1 AND SLEEP(5)", 'select * from information_schema.tables', '../../../etc/passwd', "a\0b", '<?php system($_GET[1]);', '${jndi:ldap://x}',
        ];
        foreach ($ataques as $a) {
            $this->assertNotNull(FiltroAtaques::examinar($a), "deveria recusar: $a");
        }
    }

    public function test_texto_comum_passa(): void
    {
        $normais = [
            'Comprar pão e leite', 'JavaScript: estudar closures', 'Reunião às 10h; levar o relatório', 'Ele disse "oi" #feliz',
            "it's ok", 'Selecione a união dos conjuntos', 'Desconto de 50%', 'a < b e c > d', 'Ana & Bia', 'Sleep (8 horas por noite)',
            '#2f6fc7', 'https://exemplo.com/a?b=1&c=2', 'Relatório 2026-11-09', 'O sistema caiu -- de novo', 'data: 09/11',
        ];
        foreach ($normais as $t) {
            $this->assertNull(FiltroAtaques::examinar($t), "não deveria recusar: $t");
        }
    }

    public function test_rota_de_gravacao_recusa_ataque(): void
    {
        $u = User::factory()->create();

        $this->actingAs($u)->postJson('/reclame-aqui', ['tipo' => 'ideia', 'assunto' => '<script>alert(1)</script>', 'mensagem' => 'x'])
            ->assertForbidden()->assertJsonPath('regra', 'xss.script');
        $this->actingAs($u)->postJson('/calendario/eventos?x=' . urlencode("' OR 1=1 --"), ['titulo' => 'ok'])->assertForbidden();
        $this->actingAs($u)->postJson('/reclame-aqui', ['tipo' => 'ideia', 'assunto' => 'x', 'extra' => ['fundo' => ['nome' => "1 UNION SELECT 1"]]])
            ->assertForbidden()->assertJsonPath('regra', 'sql.union');
    }

    public function test_leitura_e_senha_nao_passam_pelo_filtro(): void
    {
        $this->get('/login?x=' . urlencode('<script>'))->assertOk();
        // senha pode ter qualquer caractere
        User::factory()->create(['email' => 'c@exemplo.com', 'password' => bcrypt("' OR '1'='1")]);
        $this->post('/login', ['email' => 'c@exemplo.com', 'password' => "' OR '1'='1"])->assertRedirect(route('painel'));
    }

    public function test_quadros_continuam_gravando_codigo_e_sql(): void
    {
        $u = User::factory()->create();
        // cartões de código, SQL e HTML guardam esse texto como dado (sai escapado)
        $estado = json_encode(['blocos' => [
            ['tipo' => 'codigo', 'texto' => "<script>alert(1)</script>\nSELECT * FROM a UNION SELECT * FROM b; DROP TABLE x;"],
        ]]);
        $this->actingAs($u)->putJson('/diverso/dados/estado', ['valor' => $estado])->assertOk();
        $this->actingAs($u)->putJson('/mente/dados/estado', ['valor' => $estado])->assertOk();

        // pedaço do cartão Banco de dados: gzip em base64
        $b64 = base64_encode(gzencode("INSERT INTO t VALUES ('<b>');"));
        $this->actingAs($u)->putJson('/diverso/dados/sqlz.b1.v1.0', ['valor' => $b64])->assertOk();
        $this->assertSame($b64, DiversoDado::where('user_id', $u->id)->where('chave', 'sqlz.b1.v1.0')->value('valor'));
    }

    public function test_quadros_so_aceitam_json_valido(): void
    {
        $u = User::factory()->create();

        $this->actingAs($u)->putJson('/diverso/dados/estado', ['valor' => '{"a":'])->assertStatus(422);
        $this->actingAs($u)->putJson('/carteira/dados/estado', ['valor' => '"texto solto"'])->assertStatus(422);
        $this->actingAs($u)->putJson('/negocios/dados/img.b1', ['valor' => 'não é json'])->assertStatus(422);
        $this->actingAs($u)->putJson('/negocios/dados/img.b1', ['valor' => '"data:image/png;base64,AAA"'])->assertOk();

        // aninhado demais
        $fundo = str_repeat('[', 200) . str_repeat(']', 200);
        $this->actingAs($u)->putJson('/mente/dados/estado', ['valor' => '{"a":' . $fundo . '}'])->assertStatus(422);

        // acima do limite de tamanho (6 MB)
        $grande = '{"a":"' . str_repeat('x', 6000001) . '"}';
        $this->actingAs($u)->putJson('/diverso/dados/estado', ['valor' => $grande])->assertStatus(422);
    }

    public function test_apagar_pedacos_do_banco_nao_usa_curinga_do_like(): void
    {
        $u = User::factory()->create();
        DiversoDado::create(['user_id' => $u->id, 'chave' => 'sqlz.a_c.v1.0', 'valor' => 'AA==']);
        DiversoDado::create(['user_id' => $u->id, 'chave' => 'sqlz.abc.v1.0', 'valor' => 'AA==']);

        $this->actingAs($u)->deleteJson('/diverso/dados/sqlz.a_c')->assertOk();
        $this->assertSame(['sqlz.abc.v1.0'], DiversoDado::where('user_id', $u->id)->pluck('chave')->all());
    }
}
