<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WorkflowMembro;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Arquivos do cartão "Editor de PDF" (EditorPdfController): envio em partes, leitura e acesso. */
class EditorPdfTest extends TestCase
{
    use RefreshDatabase;

    private function pdf(int $tamanho): string
    {
        return '%PDF-1.7' . "\n" . str_repeat('x', $tamanho - 9);
    }

    private function enviar(User $u, string $area, string $arquivo, string $bytes, int $parte = 3 * 1024 * 1024, array $cab = []): \Illuminate\Testing\TestResponse
    {
        $envio = 'abc123def456';
        $partes = str_split($bytes, $parte);
        foreach ($partes as $n => $p) {
            $this->actingAs($u)->withHeaders($cab)->postJson("/editor-pdf/$area/$arquivo/parte", ['envio' => $envio, 'n' => $n, 'dados' => base64_encode($p)])->assertOk();
        }

        return $this->actingAs($u)->withHeaders($cab)->postJson("/editor-pdf/$area/$arquivo/concluir", ['envio' => $envio, 'partes' => count($partes), 'tamanho' => strlen($bytes)]);
    }

    public function test_envia_em_partes_junta_e_devolve_o_arquivo(): void
    {
        Storage::fake('local');
        $u = User::factory()->create();
        $bytes = $this->pdf(7 * 1024 * 1024 + 123); // 3 partes

        $r = $this->enviar($u, 'diverso', 'babc-x1', $bytes)->assertOk()->assertJsonStructure(['versao', 'tamanho']);
        $this->assertSame(strlen($bytes), $r->json('tamanho'));
        Storage::disk('local')->assertExists("editorpdf/{$u->id}/diverso/babc-x1.pdf");
        $this->assertSame(sha1($bytes), sha1(Storage::disk('local')->get("editorpdf/{$u->id}/diverso/babc-x1.pdf")));
        $this->assertEmpty(Storage::disk('local')->allFiles("editorpdf-envios/{$u->id}"));

        $ver = $this->actingAs($u)->get('/editor-pdf/diverso/babc-x1?v=' . $r->json('versao'));
        $ver->assertOk();
        $this->assertSame('application/pdf', $ver->headers->get('Content-Type'));

        $this->actingAs($u)->deleteJson('/editor-pdf/diverso/babc-x1')->assertOk();
        Storage::disk('local')->assertMissing("editorpdf/{$u->id}/diverso/babc-x1.pdf");
    }

    public function test_recusa_o_que_nao_e_pdf_parte_grande_e_envio_incompleto(): void
    {
        Storage::fake('local');
        $u = User::factory()->create();
        $this->actingAs($u)->postJson('/editor-pdf/corpo/bx/parte', ['envio' => 'abc123def456', 'n' => 0, 'dados' => base64_encode('<?php echo 1;')])->assertStatus(422);
        $this->actingAs($u)->postJson('/editor-pdf/corpo/bx/parte', ['envio' => 'abc123def456', 'n' => 0, 'dados' => base64_encode($this->pdf(4 * 1024 * 1024))])->assertStatus(422);
        $this->actingAs($u)->postJson('/editor-pdf/corpo/bx/parte', ['envio' => 'abc123def456', 'n' => 0, 'dados' => base64_encode($this->pdf(1000))])->assertOk();
        $this->actingAs($u)->postJson('/editor-pdf/corpo/bx/concluir', ['envio' => 'abc123def456', 'partes' => 2, 'tamanho' => 2000])->assertStatus(422);
        $this->actingAs($u)->postJson('/editor-pdf/corpo/bx/concluir', ['envio' => 'abc123def456', 'partes' => 1, 'tamanho' => 999])->assertStatus(422);
        $this->actingAs($u)->postJson('/editor-pdf/xpto/bx/parte', ['envio' => 'abc123def456', 'n' => 0, 'dados' => 'JVBERg=='])->assertNotFound();
        Storage::disk('local')->assertMissing("editorpdf/{$u->id}/corpo/bx.pdf");
    }

    public function test_cada_conta_ve_so_os_seus_e_o_convidado_le_os_do_dono(): void
    {
        Storage::fake('local');
        $dono = User::factory()->create();
        $outro = User::factory()->create();
        $this->enviar($dono, 'mente', 'bm1', $this->pdf(5000))->assertOk();

        // outra conta, sem convite: o mesmo endereço aponta para a pasta dela (não existe) e o ?de= do dono é recusado
        $this->actingAs($outro)->get('/editor-pdf/mente/bm1')->assertNotFound();
        $this->actingAs($outro)->withHeaders(['X-WF-Dono' => (string) $dono->id])->get('/editor-pdf/mente/bm1')->assertForbidden();

        // convidado só para ver: lê, mas não grava
        WorkflowMembro::create(['dono_id' => $dono->id, 'membro_id' => $outro->id, 'area' => 'mente', 'pode_editar' => false]);
        $dono->forceFill(['plano_slug' => 'premium', 'plano_expira_em' => now()->addMonth()])->save();
        $this->actingAs($outro)->withHeaders(['X-WF-Dono' => (string) $dono->id])->get('/editor-pdf/mente/bm1')->assertOk();
        $this->actingAs($outro)->withHeaders(['X-WF-Dono' => (string) $dono->id])
            ->postJson('/editor-pdf/mente/bm2/parte', ['envio' => 'abc123def456', 'n' => 0, 'dados' => base64_encode($this->pdf(100))])->assertForbidden();
    }
}
