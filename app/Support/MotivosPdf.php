<?php

namespace App\Support;

use App\Models\User;

/** PDF "Por que usar o Work Flower", baixado pelos botões da apresentação (/conheca). */
class MotivosPdf
{
    public const MOTIVOS = [
        ['Um lugar só para a vida inteira', 'Corpo, Mente, Carteira e Diverso no mesmo painel: treino, estudo, dinheiro e projetos criativos sem pular entre dez aplicativos diferentes. Menos troca de tela, mais tempo fazendo o que importa.'],
        ['Menos assinaturas, mais dinheiro no bolso', 'Editor de documentos, currículo, edição de imagem e vídeo, estúdio de música, editor de código e mapas mentais vêm juntos. Cada ferramenta que você deixa de pagar à parte vira economia todo mês.'],
        ['O caixa sempre à vista', 'A Carteira mostra entradas, saídas e metas. Você enxerga para onde o dinheiro vai, acompanha quanto falta para a meta e decide com números, não no achismo.'],
        ['Vendas dos marketplaces sem planilha', 'Conecte seus marketplaces (como o Mercado Livre) e veja pedidos e valores direto na Carteira, junto com o resto do seu dinheiro.'],
        ['Clientes e entregas no mesmo fluxo', 'Monte fluxos de trabalho, compartilhe com quem trabalha com você e acompanhe tudo sincronizado em tempo real. Entregar no prazo é o que traz o próximo cliente.'],
        ['Documentos profissionais em minutos', 'Contrato, proposta e orçamento com texto, tabela, imagem e assinatura. Exporte em PDF e guarde na biblioteca para reaproveitar no próximo serviço.'],
        ['Currículo pronto para a próxima vaga', 'Preencha uma vez e troque de modelo quando quiser. Um currículo bem apresentado abre portas para trabalhos que pagam mais.'],
        ['Estude com plano, não no improviso', 'Plano de estudos, revisão na hora certa e mapas mentais para organizar as ideias. Aprender mais rápido é ganhar mais cedo com o que você aprendeu.'],
        ['Programe direto no navegador', 'Editor de código com PHP, Laravel e Blade, JavaScript, Python e SQL com visualização das tabelas. Quando algo dá errado, a dica explica o porquê.'],
        ['Crie música sem instalar nada', 'Estúdio no estilo sequenciador: monte batidas no step sequencer, escreva melodias no piano roll, ajuste o andamento e exporte o áudio.'],
        ['Inteligência artificial com a sua chave', 'Use o assistente Claude com a sua própria chave: você paga só o que usar, direto para a Anthropic, sem mensalidade extra.'],
        ['Do seu jeito e grátis para começar', 'Cores, fundos e blocos com a sua cara. Crie a conta de graça e assine um plano só quando o Work Flower já estiver trabalhando por você.'],
    ];

    public static function gerar(?string $corPrimaria = null): string
    {
        $prim  = $corPrimaria ?: User::COR_PRIMARIA_PADRAO;
        $cab   = User::COR_CABECALHO_PADRAO;
        $tinta = '#14171b';
        $cinza = '#4b5563';
        $suave = '#eef3fb';
        $pdf   = new PdfSimples;
        $m     = 56;                          // margem lateral
        $larg  = PdfSimples::LARGURA - 2 * $m;
        $limite = PdfSimples::ALTURA - 70;    // abaixo disso começa outra página

        $pdf->novaPagina();
        // Capa: faixa no alto com o título
        $pdf->retangulo(0, 0, PdfSimples::LARGURA, 200, $cab);
        $pdf->retangulo(0, 196, PdfSimples::LARGURA, 4, $prim);
        $pdf->retangulo($m, 52, 30, 30, $prim, 8);
        $pdf->texto($m + 15, 72, 'W', 16, '#ffffff', true, 'c');
        $pdf->texto($m + 42, 72, 'WORK FLOWER', 12, '#ffffff', true);
        $pdf->texto($m, 128, 'Por que usar o Work Flower', 30, '#ffffff', true);
        $pdf->paragrafo($m, 140, $larg, '12 motivos para organizar a sua vida, economizar tempo e ganhar mais dinheiro com um painel só.', 12, '#dbe4f3');

        $y = 236;
        foreach (self::MOTIVOS as $i => [$titulo, $texto]) {
            $linhas = PdfSimples::quebrar($texto, 10.5, $larg - 44);
            $altura = 20 + count($linhas) * 14.7 + 16;
            if ($y + $altura > $limite) {
                $pdf->novaPagina();
                $pdf->retangulo(0, 0, PdfSimples::LARGURA, 6, $prim);
                $y = 56;
            }
            $pdf->retangulo($m, $y - 2, 28, 28, $prim, 7);
            $pdf->texto($m + 14, $y + 16.5, (string) ($i + 1), 12, User::contraste($prim), true, 'c');
            $pdf->texto($m + 44, $y + 14, $titulo, 13.5, $tinta, true);
            $y = $pdf->paragrafo($m + 44, $y + 18, $larg - 44, $texto, 10.5, $cinza) + 16;
        }

        // Fecho: chamada para criar a conta
        if ($y + 96 > $limite) {
            $pdf->novaPagina();
            $pdf->retangulo(0, 0, PdfSimples::LARGURA, 6, $prim);
            $y = 56;
        }
        $pdf->retangulo($m, $y, $larg, 86, $suave, 12);
        $pdf->retangulo($m, $y, 6, 86, $prim);
        $pdf->texto($m + 24, $y + 32, 'Comece hoje, de graça', 16, $tinta, true);
        $pdf->texto($m + 24, $y + 52, 'Crie a sua conta e monte o seu painel em poucos minutos:', 10.5, $cinza);
        $pdf->texto($m + 24, $y + 70, route('registro'), 11, $prim, true);

        // Rodapé em todas as páginas
        $total = $pdf->totalPaginas();
        for ($p = 0; $p < $total; $p++) {
            $pdf->irParaPagina($p);
            $pdf->retangulo($m, PdfSimples::ALTURA - 44, $larg, 0.8, '#d1d5db');
            $pdf->texto($m, PdfSimples::ALTURA - 28, 'Work Flower · Por que usar', 9, '#6b7280');
            $pdf->texto(PdfSimples::LARGURA - $m, PdfSimples::ALTURA - 28, ($p + 1) . ' / ' . $total, 9, '#6b7280', false, 'd');
        }

        return $pdf->gerar('Por que usar o Work Flower');
    }
}
