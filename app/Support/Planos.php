<?php

namespace App\Support;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Carbon;

/**
 * Limites de cada plano (fonte da verdade; public/js/planos.js espelha estes valores).
 *
 * Uso:
 *   Planos::slugDe($user)                      -> plano EFETIVO (inclui o teste grátis): 'gratuito' | 'basico' | 'pro' | 'premium'
 *   Planos::slugPago($user)                    -> plano que o usuário realmente paga (ignora o teste)
 *   Planos::emTeste($user)                     -> true durante os 30 dias de teste grátis
 *   Planos::testeAte($user)                    -> Carbon com o fim do teste, ou null
 *   Planos::limites('gratuito')                -> ['biblioteca' => 5, 'workflows' => 3, ...] (null = ilimitado)
 *   Planos::limite('gratuito', 'biblioteca')   -> 5
 *   Planos::exigir($slug, 'biblioteca', 6)     -> responde 403 JSON { plano_limite: true, ... } se passar do limite
 */
class Planos
{
    public const PADRAO = 'gratuito';

    /** Teste grátis para usuários novos: quantos dias e qual plano é liberado. */
    public const DIAS_TESTE  = 30;
    public const PLANO_TESTE = 'basico';

    /** null = ilimitado */
    private const LIMITES = [
        'gratuito' => ['biblioteca' => 5,    'workflows' => 3,    'elementos' => 10,   'usuarios' => 1],
        'basico'   => ['biblioteca' => null, 'workflows' => null, 'elementos' => null, 'usuarios' => 4],
        'pro'      => ['biblioteca' => null, 'workflows' => null, 'elementos' => null, 'usuarios' => 4],
        'premium'  => ['biblioteca' => null, 'workflows' => null, 'elementos' => null, 'usuarios' => 4],
    ];

    private const NOMES = [
        'gratuito' => 'Gratuito',
        'basico'   => 'Básico',
        'pro'      => 'Pró',
        'premium'  => 'Premium',
    ];

    /** Planos que não veem anúncios (mantenha igual a SEM_ANUNCIOS em public/js/planos.js). */
    public const SEM_ANUNCIOS = ['premium'];

    /** Slugs conhecidos. */
    public static function slugs(): array
    {
        return array_keys(self::LIMITES);
    }

    /** Nome de exibição do plano. */
    public static function nome(?string $slug): string
    {
        return self::NOMES[self::normalizar($slug)];
    }

    /** Garante um slug válido (qualquer coisa desconhecida vira o plano gratuito). */
    public static function normalizar(?string $slug): string
    {
        $slug = strtolower(trim((string) $slug));

        return isset(self::LIMITES[$slug]) ? $slug : self::PADRAO;
    }

    /* ================= Teste grátis ================= */

    /** true se o usuário ainda está dentro dos 30 dias de teste (contados a partir do cadastro). */
    public static function emTeste($usuario): bool
    {
        if (! $usuario) {
            return false;
        }

        try {
            $criado = $usuario->created_at ?? null;
            if (! $criado) {
                return false;
            }

            return Carbon::parse($criado)->addDays(self::DIAS_TESTE)->isFuture();
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }

    /** Quando o teste termina; null se não há teste ativo (já passou ou o usuário já paga um plano). */
    public static function testeAte($usuario): ?Carbon
    {
        if (! self::emTeste($usuario) || self::slugPago($usuario) !== self::PADRAO) {
            return null;
        }

        return Carbon::parse($usuario->created_at)->addDays(self::DIAS_TESTE);
    }

    /**
     * Plano EFETIVO do usuário: é este que alimenta limites, metas, anúncios e o resto do app.
     * Quem não paga nada mas está no teste recebe PLANO_TESTE; passados os 30 dias volta ao gratuito sozinho.
     */
    public static function slugDe($usuario): string
    {
        $slug = self::slugPago($usuario);

        if ($slug === self::PADRAO && self::emTeste($usuario)) {
            return self::PLANO_TESTE;
        }

        return $slug;
    }

    /**
     * Plano que o usuário realmente paga (ignora o teste grátis). Use este na tela de planos
     * ("Plano atual") e no AssinaturaController.
     * Aceita as formas mais comuns de guardar o plano; sem usuário ou sem plano definido, vale o gratuito.
     */
    public static function slugPago($usuario): string
    {
        if (! $usuario) {
            return self::PADRAO;
        }

        try {
            // coluna direta no usuário
            foreach (['plano_slug', 'plan_slug', 'plano', 'plan'] as $campo) {
                $valor = $usuario->{$campo} ?? null;
                if (is_string($valor) && $valor !== '') {
                    $slug = self::normalizar($valor);

                    // plano pago vencido (além da tolerância) volta para o gratuito
                    $expira = $usuario->plano_expira_em ?? null;
                    if ($slug !== self::PADRAO && $expira
                        && Carbon::parse($expira)->addDays((int) config('asaas.grace_days', 3))->isPast()) {
                        return self::PADRAO;
                    }

                    return $slug;
                }
            }

            // relação "plan" / "plano" com slug
            foreach (['plan', 'plano'] as $relacao) {
                $obj = $usuario->{$relacao} ?? null;
                if (is_object($obj) && ! empty($obj->slug)) {
                    return self::normalizar($obj->slug);
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return self::PADRAO;
    }

    /** true se o usuário está num plano sem anúncios (usa slugDe, então plano vencido volta a ver anúncios). */
    public static function semAnuncios($usuario): bool
    {
        return in_array(self::slugDe($usuario), self::SEM_ANUNCIOS, true);
    }

    /**
     * Quantos elementos (texto, tabela, lista etc.) há num valor guardado no banco.
     * Conta objetos { tipo: string, dados: objeto }, a mesma regra de WFPlanos.contar() em planos.js.
     * Valor que não é JSON (ou não tem blocos) conta 0.
     */
    public static function contarElementos($valor): int
    {
        if (! is_string($valor) || $valor === '' || ($valor[0] !== '{' && $valor[0] !== '[')) {
            return 0;
        }

        $json = json_decode($valor, true);
        if (! is_array($json)) {
            return 0;
        }

        $total = 0;
        $andar = function ($o, int $nivel) use (&$andar, &$total) {
            if (! is_array($o) || $nivel > 50) {
                return;
            }
            if (isset($o['tipo'], $o['dados']) && is_string($o['tipo']) && is_array($o['dados'])
                && ! array_is_list($o['dados'])) {
                $total++;
            }
            foreach ($o as $v) {
                if (is_array($v)) {
                    $andar($v, $nivel + 1);
                }
            }
        };
        $andar($json, 0);

        return $total;
    }

    /** Limites do plano (todos os recursos). */
    public static function limites(?string $slug): array
    {
        return self::LIMITES[self::normalizar($slug)];
    }

    /** Limite de um recurso: número ou null (ilimitado). */
    public static function limite(?string $slug, string $recurso): ?int
    {
        return self::limites($slug)[$recurso] ?? null;
    }

    /** true se a quantidade passa do limite do plano. */
    public static function excede(?string $slug, string $recurso, int $quantidade): bool
    {
        $limite = self::limite($slug, $recurso);

        return $limite !== null && $quantidade > $limite;
    }

    /**
     * Barra a requisição quando a quantidade passa do limite:
     * 403 JSON { plano_limite, recurso, limite, message, redirect }
     * (o planos.js transforma isso em aviso + redirecionamento para a tela de planos).
     */
    public static function exigir(?string $slug, string $recurso, int $quantidade, string $acao = 'guardar'): void
    {
        if (! self::excede($slug, $recurso, $quantidade)) {
            return;
        }

        $slug   = self::normalizar($slug);
        $limite = self::limite($slug, $recurso);

        throw new HttpResponseException(response()->json([
            'plano_limite' => true,
            'recurso'      => $recurso,
            'limite'       => $limite,
            'message'      => self::mensagem($slug, $recurso, $limite, $acao),
            'redirect'     => self::urlPlanos(),
        ], 403));
    }

    private static function mensagem(string $slug, string $recurso, int $limite, string $acao): string
    {
        $nome = self::NOMES[$slug];

        return match ($recurso) {
            'biblioteca' => "O plano {$nome} guarda até {$limite} blocos na biblioteca. Assine o Básico para guardar sem limite.",
            'workflows'  => "O plano {$nome} permite {$acao} até {$limite} workflows por vez. Assine o Básico para não ter limite.",
            'elementos'  => "O plano {$nome} permite {$acao} até {$limite} elementos (texto, tabelas etc.) por arquivo. Assine o Básico para não ter limite.",
            'usuarios'   => "O plano {$nome} permite {$limite} " . ($limite === 1 ? 'usuário' : 'usuários') . ' na conta. Assine o Básico para ter até 4.',
            default      => "O plano {$nome} atingiu o limite de {$recurso}. Assine o Básico para continuar.",
        };
    }

    private static function urlPlanos(): string
    {
        try {
            return \Illuminate\Support\Facades\Route::has('planos') ? route('planos') : url('/planos');
        } catch (\Throwable $e) {
            return '/planos';
        }
    }
}
