<?php

namespace App\Support;

/**
 * Simulação de aportes mensais com dividendos (reinvestidos ou não), mês a mês.
 * Hipóteses simples e constantes: DY e valorização anuais fixos, sem impostos, taxas, vacância ou inflação.
 */
class SimuladorInvest
{
    /**
     * @param  array{inicial:float,mensal:float,meses:int,reinvestir:bool,alvo:float}  $p
     * @param  array{nome:string,dy:float,valorizacao:float}  $ativo  DY e valorização em % ao ano
     */
    public static function rodar(array $p, array $ativo): array
    {
        $meses = max(1, min(600, (int) $p['meses']));
        $dm = max(0.0, (float) $ativo['dy']) / 100 / 12;
        $g = pow(1 + max(-90.0, (float) $ativo['valorizacao']) / 100, 1 / 12) - 1;
        $alvo = max(0.0, (float) $p['alvo']);

        $patrimonio = max(0.0, (float) $p['inicial']);
        $aportado = $patrimonio;
        $recebido = 0.0;
        $mesAlvo = null;
        $serie = [];
        $renda = 0.0;

        for ($m = 1; $m <= $meses; $m++) {
            $patrimonio *= 1 + $g;
            $renda = $patrimonio * $dm;
            $recebido += $renda;
            if ($p['reinvestir']) {
                $patrimonio += $renda;
            }
            $patrimonio += max(0.0, (float) $p['mensal']);
            $aportado += max(0.0, (float) $p['mensal']);
            if ($mesAlvo === null && $alvo > 0 && $renda >= $alvo) {
                $mesAlvo = $m;
            }
            $serie[] = ['mes' => $m, 'patrimonio' => round($patrimonio, 2), 'renda' => round($renda, 2), 'aportado' => round($aportado, 2)];
        }

        // série enxuta para o gráfico (até 120 pontos, sempre com o último mês)
        $passo = (int) ceil($meses / 120);
        $pontos = array_values(array_filter($serie, fn ($s) => $s['mes'] % $passo === 0 || $s['mes'] === $meses));

        return [
            'nome' => $ativo['nome'],
            'dy' => round((float) $ativo['dy'], 2),
            'valorizacao' => round((float) $ativo['valorizacao'], 2),
            'patrimonioFinal' => round($patrimonio, 2),
            'aportado' => round($aportado, 2),
            'dividendosRecebidos' => round($recebido, 2),
            'rendaMensalFinal' => round($renda, 2),
            'mesesAteAlvo' => $mesAlvo,
            // patrimônio que paga a renda-alvo com esse DY
            'patrimonioParaAlvo' => $alvo > 0 && $dm > 0 ? round($alvo / $dm, 2) : null,
            'serie' => $pontos,
        ];
    }
}
