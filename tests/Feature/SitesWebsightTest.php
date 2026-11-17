<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/** /admin/sites-websight: quem do Work Flower também está nos outros sites da Websight (só leitura). */
class SitesWebsightTest extends TestCase
{
    use RefreshDatabase;

    private array $arquivos = [];

    protected function tearDown(): void
    {
        foreach ($this->arquivos as $a) @unlink($a);
        parent::tearDown();
    }

    private function bancoCom(string $tabela, array $emails): string
    {
        $arq = tempnam(sys_get_temp_dir(), 'ws') . '.sqlite';
        $this->arquivos[] = $arq;
        $pdo = new \PDO('sqlite:' . $arq);
        $pdo->exec("CREATE TABLE $tabela (id INTEGER PRIMARY KEY, email TEXT)");
        $st = $pdo->prepare("INSERT INTO $tabela (email) VALUES (?)");
        foreach ($emails as $e) $st->execute([$e]);
        $pdo = null;

        return $arq;
    }

    private function admin(): User
    {
        return User::where('email', User::ADMINS[0])->first() ?? User::factory()->create(['email' => User::ADMINS[0]]);
    }

    public function test_mostra_quem_esta_em_cada_site(): void
    {
        Cache::flush();
        $ana = User::factory()->create(['email' => 'ana@exemplo.com', 'name' => 'Ana']);
        User::factory()->create(['email' => 'bia@exemplo.com', 'name' => 'Bia']);
        config([
            'websight.sites.praga.db' => $this->bancoCom('users', ['ANA@exemplo.com', 'outro@x.com']),
            'websight.sites.trabalhos.db' => $this->bancoCom('pedidos', ['bia@exemplo.com', null]),
            'websight.sites.ged.db' => '/nao/existe.sqlite',
        ]);

        $r = $this->actingAs($this->admin())->get('/admin/sites-websight')->assertOk();
        $r->assertSee('Praga Vermelha')->assertSee('Banco não encontrado.')->assertSee('WEBSIGHT_WORLDBUILDER_DB', false);

        // só quem está no Praga Vermelha
        $this->actingAs($this->admin())->get('/admin/sites-websight?site=praga')->assertOk()
            ->assertSee('ana@exemplo.com')->assertDontSee('bia@exemplo.com');
        $this->actingAs($this->admin())->get('/admin/sites-websight?site=trabalhos')->assertOk()
            ->assertSee('bia@exemplo.com')->assertDontSee($ana->email);
    }

    public function test_so_admin_entra(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin/sites-websight')->assertNotFound();
    }
}
