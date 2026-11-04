<?php

namespace App\Support\Emails;

use App\Models\EmailRegra;
use App\Models\NegocioDado;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Categorias automáticas dos e-mails. Cada pessoa edita as próprias regras (remetente/assunto contém...);
 * a primeira que bate decide. Quem está na lista de Clientes da área Negócios cai em "Clientes" antes de tudo.
 * Sem regra nenhuma batendo, fica em "Outros".
 */
class Classificador
{
    public const PADRAO = 'Outros';
    public const CATEGORIAS = ['Clientes', 'Financeiro', 'Promoções', 'Outros'];

    /** Regras de quem ainda não mexeu em nada. */
    public const REGRAS_INICIAIS = [
        ['Financeiro', 'assunto', 'boleto'], ['Financeiro', 'assunto', 'fatura'], ['Financeiro', 'assunto', 'nota fiscal'],
        ['Financeiro', 'assunto', 'nf-e'], ['Financeiro', 'assunto', 'pagamento'], ['Financeiro', 'assunto', 'pix'],
        ['Financeiro', 'assunto', 'cobrança'], ['Financeiro', 'assunto', 'comprovante'], ['Financeiro', 'assunto', 'extrato'],
        ['Financeiro', 'assunto', 'recibo'], ['Financeiro', 'remetente', 'banco'], ['Financeiro', 'remetente', 'nubank'],
        ['Financeiro', 'remetente', 'mercadopago'], ['Financeiro', 'remetente', 'pagseguro'],
        ['Clientes', 'assunto', 'orçamento'], ['Clientes', 'assunto', 'pedido'], ['Clientes', 'assunto', 'cotação'],
        ['Clientes', 'assunto', 'proposta'], ['Clientes', 'assunto', 'contrato'], ['Clientes', 'assunto', 'encomenda'],
        ['Promoções', 'assunto', 'promoção'], ['Promoções', 'assunto', 'oferta'], ['Promoções', 'assunto', 'desconto'],
        ['Promoções', 'assunto', 'cupom'], ['Promoções', 'assunto', 'black friday'], ['Promoções', 'assunto', '% off'],
        ['Promoções', 'remetente', 'newsletter'], ['Promoções', 'remetente', 'marketing'], ['Promoções', 'remetente', 'promo'],
    ];

    /** @return Collection<int, EmailRegra> as regras da pessoa, criando as iniciais na primeira vez */
    public static function regras(int $userId): Collection
    {
        $regras = EmailRegra::where('user_id', $userId)->orderBy('ordem')->orderBy('id')->get();
        // quem apaga todas as regras fica com uma "vazia" guardada (termo ''), que não bate com nada e impede recriar as iniciais
        if ($regras->isEmpty()) {
            foreach (self::REGRAS_INICIAIS as $i => [$cat, $campo, $termo]) {
                EmailRegra::create(['user_id' => $userId, 'categoria' => $cat, 'campo' => $campo, 'termo' => $termo, 'ordem' => $i]);
            }
            $regras = EmailRegra::where('user_id', $userId)->orderBy('ordem')->orderBy('id')->get();
        }

        return $regras;
    }

    /** E-mails escritos no campo "contato" dos clientes da área Negócios. @return array<string, true> */
    public static function emailsDeClientes(int $userId): array
    {
        $bruto = NegocioDado::where('user_id', $userId)->where('chave', 'estado')->value('valor');
        $estado = $bruto ? json_decode($bruto, true) : null;
        $saida = [];
        foreach ((array) ($estado['clientes'] ?? []) as $c) {
            if (preg_match_all('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', (string) ($c['contato'] ?? ''), $m)) {
                foreach ($m[0] as $e) $saida[mb_strtolower($e)] = true;
            }
        }

        return $saida;
    }

    public static function categoria(iterable $regras, array $clientes, ?string $deNome, ?string $deEmail, ?string $assunto): string
    {
        if ($deEmail && isset($clientes[mb_strtolower($deEmail)])) return 'Clientes';

        $remetente = self::normal(($deNome ?? '') . ' ' . ($deEmail ?? ''));
        $titulo = self::normal($assunto ?? '');
        foreach ($regras as $r) {
            $termo = self::normal($r->termo ?? $r['termo'] ?? '');
            if ($termo === '') continue;
            $campo = $r->campo ?? $r['campo'] ?? 'qualquer';
            $onde = match ($campo) {
                'remetente' => $remetente,
                'assunto'   => $titulo,
                default     => $remetente . ' ' . $titulo,
            };
            if (str_contains($onde, $termo)) return $r->categoria ?? $r['categoria'];
        }

        return self::PADRAO;
    }

    /** Sem acento e em minúsculas, para "Cobrança" bater com "cobranca". */
    private static function normal(string $s): string
    {
        return mb_strtolower(Str::ascii($s));
    }
}
