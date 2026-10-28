<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\WorkflowMembro;
use App\Support\Compartilhar;
use App\Support\Planos;
use App\Support\Sincronia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Convidar pessoas para um workflow (área), tirar o acesso, sair de um workflow compartilhado e
 * conferir mudanças feitas pelas outras pessoas (sincronia).
 */
class CompartilharController extends Controller
{
    private function area(string $area): string
    {
        abort_unless(in_array($area, Compartilhar::areas(), true), 404);

        return $area;
    }

    /** GET /compartilhar/{area}: pessoas desta área, limite do plano e workflows compartilhados comigo. */
    public function index(Request $request, string $area): JsonResponse
    {
        $area = $this->area($area);
        $eu = $request->user();
        $ativos = Compartilhar::convidadosAtivos($eu);
        $todos = Compartilhar::convidados($eu->id);
        $limite = Compartilhar::limitePessoas($eu);

        $membros = WorkflowMembro::with('membro:id,name,email')
            ->where(['dono_id' => $eu->id, 'area' => $area])->orderBy('created_at')->get()
            ->map(fn ($m) => [
                'id' => $m->membro_id, 'nome' => $m->membro?->name, 'email' => $m->membro?->email,
                'ativo' => in_array($m->membro_id, $ativos, true),
            ])->values();

        $comigo = WorkflowMembro::with('dono:id,name')->where('membro_id', $eu->id)->orderBy('area')->get()
            ->map(fn ($m) => [
                'dono' => $m->dono_id, 'nome' => $m->dono?->name, 'area' => $m->area,
                'areaNome' => \App\Models\Tarefa::AREAS[$m->area]['nome'] ?? $m->area,
                'ativo' => Compartilhar::podeAcessar($eu->id, $m->dono_id, $m->area),
                'url' => url('/areas/' . $m->area) . '?de=' . $m->dono_id,
            ])->values();

        return response()->json([
            'membros' => $membros,
            'pessoas' => 1 + count($todos),          // na conta, com o dono
            'limite' => $limite,                      // null = sem limite
            'plano' => Planos::nome(Planos::slugDe($eu)),
            'semAcesso' => count($todos) - count($ativos),
            'comigo' => $comigo,
        ]);
    }

    /** POST /compartilhar/{area} { email }: dá acesso a esta área para uma conta existente. */
    public function adicionar(Request $request, string $area): JsonResponse
    {
        $area = $this->area($area);
        $dados = $request->validate(['email' => ['required', 'email', 'max:190']], [
            'email.required' => 'Digite o e-mail da pessoa.', 'email.email' => 'Digite um e-mail válido.',
        ]);
        $eu = $request->user();
        $outro = User::where('email', mb_strtolower(trim($dados['email'])))->orWhere('email', trim($dados['email']))->first();

        if (! $outro) {
            return response()->json(['message' => 'Não há conta com esse e-mail. Peça para a pessoa se cadastrar no Work Flower e tente de novo.'], 422);
        }
        if ($outro->id === $eu->id) {
            return response()->json(['message' => 'Esse é o seu próprio e-mail.'], 422);
        }
        if (WorkflowMembro::where(['dono_id' => $eu->id, 'membro_id' => $outro->id, 'area' => $area])->exists()) {
            return response()->json(['message' => 'Essa pessoa já tem acesso a este workflow.'], 422);
        }

        // limite do plano: pessoas na conta (com o dono). Quem já está em outra área não conta de novo.
        $todos = Compartilhar::convidados($eu->id);
        if (! in_array($outro->id, $todos, true)) {
            Planos::exigir(Planos::slugDe($eu), 'usuarios', 1 + count($todos) + 1, 'ter');
        }

        WorkflowMembro::create(['dono_id' => $eu->id, 'membro_id' => $outro->id, 'area' => $area]);

        return response()->json(['ok' => true, 'message' => "{$outro->name} agora tem acesso a este workflow."]);
    }

    /** DELETE /compartilhar/{area}/{membro}: o dono tira o acesso de alguém. */
    public function remover(Request $request, string $area, int $membro): JsonResponse
    {
        $area = $this->area($area);
        WorkflowMembro::where(['dono_id' => $request->user()->id, 'membro_id' => $membro, 'area' => $area])->delete();

        return response()->json(['ok' => true]);
    }

    /** DELETE /compartilhar/{area}/sair/{dono}: o convidado sai do workflow de outra pessoa. */
    public function sair(Request $request, string $area, int $dono): JsonResponse
    {
        $area = $this->area($area);
        WorkflowMembro::where(['dono_id' => $dono, 'membro_id' => $request->user()->id, 'area' => $area])->delete();

        return response()->json(['ok' => true]);
    }

    /** GET /sincronia/{area}?versao=...&desde=...: o que as outras pessoas mudaram (estado e imagens). */
    public function sincronia(Request $request, string $area): JsonResponse
    {
        $area = $this->area($area);
        $dono = Compartilhar::dono($request, $area);

        return response()->json(Sincronia::mudancas($area, $dono, (string) $request->query('versao', ''), $request->query('desde')));
    }
}
