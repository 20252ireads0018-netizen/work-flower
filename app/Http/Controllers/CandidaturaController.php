<?php

namespace App\Http\Controllers;

use App\Models\Candidatura;
use App\Support\Pix;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CandidaturaController extends Controller
{
    private const ESCOLARIDADE = [
        'Ensino médio completo', 'Ensino técnico', 'Superior em andamento', 'Superior completo',
    ];
    private const EXPERIENCIA = [
        'Nenhuma ainda', 'Menos de 1 ano', 'De 1 a 3 anos', 'Mais de 3 anos',
    ];
    private const DIAS = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'];
    private const TURNOS = [
        'Manhã (6h às 12h)', 'Tarde (12h às 18h)', 'Noite (18h às 23h)', 'Varia conforme o dia',
    ];

    public function index()
    {
        return view('vagas.index', ['preco' => (float) config('vagas.curso_preco', 19.90)]);
    }

    /**
     * Compra do curso (opcional, separada da candidatura): gera o Pix com valor fixo na chave da plataforma.
     * Chamar de novo devolve o mesmo Pix (mesmo identificador), para não gerar vários pedidos.
     */
    public function pedidoCurso(Request $request): JsonResponse
    {
        $c = $this->daCandidatura($request);
        if (! Pix::pronto()) {
            return response()->json(['ok' => false, 'erro' => 'O pagamento por Pix está indisponível agora. Tente de novo mais tarde.'], 503);
        }
        if ($c->curso_status !== 'confirmado') {
            $c->forceFill([
                'curso_txid'      => $c->curso_txid ?: Pix::novoTxid(),
                'curso_status'    => in_array($c->curso_status, ['informado', 'recusado'], true) ? $c->curso_status : 'aguardando',
                'curso_valor'     => (float) config('vagas.curso_preco', 19.90),
                'curso_pedido_em' => $c->curso_pedido_em ?: now(),
            ])->save();
        }

        return response()->json($this->resumoCurso($c));
    }

    /** "Já paguei": avisa a administração, que confere o extrato e confirma em /admin/pix. */
    public function cursoPaguei(Request $request): JsonResponse
    {
        $c = $this->daCandidatura($request);
        $d = $request->validate(['pagador' => ['nullable', 'string', 'max:120']]);
        abort_unless($c->curso_txid, 404);
        if (in_array($c->curso_status, ['aguardando', 'recusado'], true)) {
            $c->forceFill([
                'curso_status'       => 'informado',
                'curso_informado_em' => now(),
                'curso_pagador'      => trim((string) ($d['pagador'] ?? '')) ?: $c->nome,
                'curso_obs'          => null,
            ])->save();
        }

        return response()->json($this->resumoCurso($c));
    }

    private function daCandidatura(Request $request): Candidatura
    {
        $codigo = (string) $request->input('codigo');
        abort_unless(Str::isUuid($codigo), 404);

        return Candidatura::where('codigo', $codigo)->firstOrFail();
    }

    private function resumoCurso(Candidatura $c): array
    {
        $valor = (float) ($c->curso_valor ?? config('vagas.curso_preco', 19.90));

        return [
            'ok'     => true,
            'status' => $c->curso_status,
            'valor'  => $valor,
            'txid'   => $c->curso_txid,
            'copia'  => $c->curso_status === 'confirmado' ? null : Pix::copiaECola($valor, $c->curso_txid),
            'obs'    => $c->curso_obs,
        ];
    }

    public function store(Request $request): JsonResponse
    {
        // Campo-isca: humanos não preenchem; robôs sim.
        if ($request->filled('site')) {
            return response()->json(['ok' => true, 'codigo' => (string) Str::uuid(), 'curso_url' => null]);
        }

        $request->merge(['fone' => preg_replace('/\D+/', '', (string) $request->input('fone'))]);

        $dados = $request->validate([
            'codigo' => ['nullable', 'uuid'],
            'nome' => ['required', 'string', 'max:120', function ($attr, $value, $fail) {
                $partes = preg_split('/\s+/', trim((string) $value), -1, PREG_SPLIT_NO_EMPTY);
                if (count($partes) < 2) {
                    $fail('Digite nome e sobrenome.');
                }
            }],
            'email' => ['required', 'email:rfc', 'max:150'],
            'fone' => ['required', 'digits_between:10,13'],
            'cidade' => ['required', 'string', 'min:3', 'max:120'],
            'escolaridade' => ['required', Rule::in(self::ESCOLARIDADE)],
            'exp' => ['required', Rule::in(self::EXPERIENCIA)],
            'dias' => ['required', 'array', 'min:1'],
            'dias.*' => ['string', Rule::in(self::DIAS)],
            'turno' => ['required', Rule::in(self::TURNOS)],
            'horas' => ['required', 'integer', 'between:10,25'],
            'msg' => ['nullable', 'string', 'max:2000'],
            'equip' => ['accepted'],
            'lgpd' => ['accepted'],
        ], [
            'required' => 'Preencha este campo.',
            'email' => 'Digite um e-mail válido, como nome@exemplo.com.',
            'fone.digits_between' => 'Digite o WhatsApp com DDD.',
            'cidade.min' => 'Informe sua cidade e estado.',
            'in' => 'Selecione uma opção válida.',
            'dias.required' => 'Marque pelo menos um dia.',
            'dias.min' => 'Marque pelo menos um dia.',
            'equip.accepted' => 'Esta vaga exige computador e internet estável.',
            'lgpd.accepted' => 'Sem esta autorização não podemos analisar a candidatura.',
        ]);

        $campos = [
            'nome' => trim($dados['nome']),
            'email' => Str::lower(trim($dados['email'])),
            'fone' => $dados['fone'],
            'cidade' => trim($dados['cidade']),
            'escolaridade' => $dados['escolaridade'],
            'exp' => $dados['exp'],
            'dias' => array_values(array_unique($dados['dias'])),
            'turno' => $dados['turno'],
            'horas' => (int) $dados['horas'],
            'msg' => isset($dados['msg']) ? trim($dados['msg']) : null,
        ];

        // "Editar respostas" reenvia com o mesmo código: atualiza em vez de duplicar.
        $candidatura = ! empty($dados['codigo'])
            ? Candidatura::where('codigo', $dados['codigo'])->first()
            : null;

        if ($candidatura) {
            $candidatura->update($campos);
        } else {
            $candidatura = Candidatura::create($campos + [
                'codigo' => (string) Str::uuid(),
                'lgpd_aceito_em' => now(),
                'ip' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
            ]);
        }

        return response()->json([
            'ok' => true,
            'codigo' => $candidatura->codigo,
            'curso_url' => route('vagas.curso', ['c' => $candidatura->codigo]),
        ]);
    }

    /** A aba do curso aponta para cá; registra o clique e redireciona para o link configurado. */
    public function curso(Request $request)
    {
        $url = config('vagas.curso_url');
        abort_if(blank($url), 404);

        $codigo = $request->query('c');
        if (is_string($codigo) && Str::isUuid($codigo)) {
            Candidatura::where('codigo', $codigo)
                ->whereNull('curso_clicado_em')
                ->update(['curso_clicado_em' => now()]);
        }

        return redirect()->away($url);
    }

    /** Painel simples: /vagas/admin?token=SEU_TOKEN (&formato=csv para baixar). */
    public function admin(Request $request)
    {
        $token = (string) config('vagas.admin_token');
        abort_if($token === '' || ! hash_equals($token, (string) $request->query('token')), 404);

        $candidaturas = Candidatura::latest()->get();

        if ($request->query('formato') === 'csv') {
            return response()->streamDownload(function () use ($candidaturas) {
                $out = fopen('php://output', 'w');
                fwrite($out, "\xEF\xBB\xBF");
                fputcsv($out, ['Data', 'Nome', 'E-mail', 'WhatsApp', 'Cidade', 'Escolaridade',
                    'Experiência', 'Dias', 'Período', 'Horas/sem', 'Mensagem', 'Curso (Pix)', 'Pagador', 'Identificador do Pix']);
                foreach ($candidaturas as $c) {
                    fputcsv($out, array_map([$this, 'csvSeguro'], [
                        $c->created_at?->format('d/m/Y H:i'), $c->nome, $c->email, $c->fone, $c->cidade,
                        $c->escolaridade, $c->exp, implode(', ', $c->dias), $c->turno, $c->horas,
                        $c->msg, $c->curso_status ? $c->cursoSituacao() . ($c->curso_confirmado_em ? ' em ' . $c->curso_confirmado_em->format('d/m/Y H:i') : '') : '', $c->curso_pagador, $c->curso_txid,
                    ]));
                }
                fclose($out);
            }, 'candidaturas.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        return view('vagas.admin', compact('candidaturas'));
    }

    /** Evita injeção de fórmula ao abrir o CSV no Excel/Sheets. */
    private function csvSeguro($valor): string
    {
        $valor = (string) $valor;
        return preg_match('/^[=+\-@\t\r]/', $valor) ? "'" . $valor : $valor;
    }
}
