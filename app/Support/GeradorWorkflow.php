<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Gerador de workflows por tema ("Workflows prontos" › "Criar com o meu tema"): a pessoa escreve um tema
 * ("Academia e dieta", "Aprender violão", "ENEM 2027") e nasce uma área com abas e cartões montados por
 * palavras-chave (sem IA: módulos fixos combinados), cores próprias, imagens de bancos gratuitos com licença
 * livre (ImagensLivres) e um cartão com os créditos. Cria pelo mesmo caminho dos prontos (WorkflowsProntos::criarDeTema).
 */
class GeradorWorkflow
{
    /** módulo => [palavras-chave, busca de imagem (inglês rende mais no Openverse), aba [nome, ícone, cartões]] */
    private static function modulos(string $tema): array
    {
        return [
            'treino' => [['academia', 'treino', 'musculacao', 'corrida', 'fitness', 'esporte', 'malhar', 'crossfit', 'natacao', 'futebol', 'ciclismo', 'yoga'],
                'gym', ['Treinos', 'saude', [
                    ['tabela', '🏋️ Treino da semana', ['Dia', 'Treino', 'Feito'], [['Seg', '', ''], ['Qua', '', ''], ['Sex', '', '']]],
                    ['tabela', '📈 Cargas e marcas', ['Exercício', 'Carga', 'Data'], [['', '', ''], ['', '', '']]],
                    ['lista', '🎯 Metas do treino', ['Treinar 3x por semana', 'Dormir 7h por noite']],
                ]]],
            'dieta' => [['dieta', 'alimentacao', 'emagrecer', 'nutricao', 'receita', 'receitas', 'cozinha', 'comida', 'saudavel'],
                'salad', ['Alimentação', 'saude', [
                    ['tabela', '🥗 Cardápio da semana', ['Dia', 'Almoço', 'Jantar'], [['Seg', '', ''], ['Ter', '', ''], ['Qua', '', ''], ['Qui', '', ''], ['Sex', '', '']]],
                    ['lista', '🛒 Lista de compras', ['Frutas', 'Verduras', 'Proteínas']],
                ]]],
            'estudo' => [['estudo', 'estudos', 'estudar', 'prova', 'provas', 'enem', 'vestibular', 'concurso', 'faculdade', 'curso', 'aprender', 'escola', 'tcc', 'mestrado'],
                'studying', ['Estudos', 'estudo', [
                    ['tabela', '🗓️ Cronograma', ['Dia', 'Matéria', 'Tempo'], [['Seg', '', '1h'], ['Ter', '', '1h'], ['Qua', '', '1h'], ['Qui', '', '1h'], ['Sex', 'Revisão', '1h']]],
                    ['lista', '🔁 Revisões da semana', ['Revisar o conteúdo de segunda', 'Refazer os exercícios errados']],
                    ['texto', '📝 Resumo do dia', ['O que aprendi hoje:', '- ', '', 'Dúvidas para tirar:', '- ']],
                ]]],
            'idioma' => [['ingles', 'espanhol', 'frances', 'alemao', 'italiano', 'japones', 'coreano', 'idioma', 'idiomas', 'lingua'],
                'dictionary', ['Idioma', 'globo', [
                    ['tabela', '🗣️ Vocabulário novo', ['Palavra', 'Tradução', 'Exemplo'], [['', '', ''], ['', '', ''], ['', '', '']]],
                    ['lista', '🎧 Rotina de prática', ['15 min de app ou aula', 'Ouvir um podcast', 'Escrever 3 frases']],
                ]]],
            'financas' => [['financas', 'dinheiro', 'orcamento', 'economizar', 'investir', 'investimento', 'dividas', 'divida', 'poupar', 'salario', 'gastos'],
                'coins', ['Finanças', 'dinheiro', [
                    ['tabela', '💰 Orçamento do mês', ['Categoria', 'Previsto', 'Gasto'], [['Moradia', '', ''], ['Mercado', '', ''], ['Transporte', '', ''], ['Lazer', '', '']]],
                    ['lista', '🎯 Metas financeiras', ['Montar a reserva de emergência', 'Quitar a fatura do cartão']],
                ]]],
            'projeto' => [['projeto', 'projetos', 'trabalho', 'empresa', 'negocio', 'lancamento', 'startup', 'cliente', 'clientes', 'freela', 'loja', 'marca', 'produto', 'app', 'site'],
                'office', ['Projeto', 'trabalho', [
                    ['lista', '🧭 Etapas', ['Definir o objetivo', 'Planejar as entregas', 'Executar', 'Revisar e lançar']],
                    ['tabela', '📋 Tarefas', ['Tarefa', 'Responsável', 'Prazo'], [['', '', ''], ['', '', ''], ['', '', '']]],
                ]]],
            'leitura' => [['leitura', 'livro', 'livros', 'ler', 'literatura', 'clube'],
                'library', ['Leituras', 'livro', [
                    ['tabela', '📚 Lista de leitura', ['Livro', 'Autor', 'Status'], [['', '', 'Quero ler'], ['', '', 'Lendo']]],
                    ['texto', '✍️ Anotações', ['Trechos favoritos:', '- ', '', 'O que levo deste livro:', '- ']],
                ]]],
            'musica' => [['violao', 'guitarra', 'musica', 'piano', 'teclado', 'canto', 'cantar', 'bateria', 'baixo', 'ukulele', 'banda'],
                'guitar', ['Prática', 'musica', [
                    ['tabela', '🎸 Rotina de prática', ['Dia', 'O que praticar', 'Minutos'], [['Seg', 'Técnica', '20'], ['Qua', 'Repertório', '20'], ['Sex', 'Livre', '30']]],
                    ['lista', '🎵 Repertório', ['Música 1', 'Música 2']],
                ]]],
            'viagem' => [['viagem', 'viajar', 'ferias', 'intercambio', 'mochilao', 'turismo', 'praia'],
                'landscape', ['Viagem', 'viagem', [
                    ['tabela', '🗺️ Roteiro', ['Dia', 'Lugar', 'O que fazer'], [['1', '', ''], ['2', '', ''], ['3', '', '']]],
                    ['lista', '🧳 Mala', ['Documentos', 'Carregadores', 'Remédios']],
                    ['tabela', '💸 Gastos', ['Item', 'Valor'], [['Passagem', ''], ['Hospedagem', '']]],
                ]]],
            'evento' => [['casamento', 'festa', 'evento', 'aniversario', 'formatura', 'cha'],
                'celebration', ['Evento', 'estrela', [
                    ['lista', '✅ Checklist', ['Definir a data', 'Reservar o local', 'Enviar os convites']],
                    ['tabela', '👥 Convidados', ['Nome', 'Confirmou'], [['', ''], ['', '']]],
                    ['tabela', '💵 Orçamento', ['Item', 'Valor'], [['Local', ''], ['Comida', ''], ['Decoração', '']]],
                ]]],
            'casa' => [['casa', 'mudanca', 'reforma', 'limpeza', 'apartamento', 'decoracao', 'jardim'],
                'interior', ['Casa', 'casa', [
                    ['lista', '🏠 Tarefas da casa', ['Organizar um cômodo por semana', 'Fazer a lista de consertos']],
                    ['tabela', '🛠️ Compras e consertos', ['Item', 'Onde', 'Valor'], [['', '', ''], ['', '', '']]],
                ]]],
            'habitos' => [['saude', 'sono', 'meditacao', 'ansiedade', 'bem', 'habitos', 'habito', 'rotina', 'mental', 'produtividade', 'foco'],
                'sunrise', ['Hábitos', 'check', [
                    ['tabela', '🌱 Hábitos da semana', ['Hábito', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex'], [['Beber água', '', '', '', '', ''], ['Dormir cedo', '', '', '', '', '']]],
                    ['texto', '📓 Diário', ['Como me senti hoje:', '', 'Uma coisa boa do dia:']],
                ]]],
        ];
    }

    /** Paletas (fundo + cores dos cartões), escolhidas pelo tema. */
    private const PALETAS = [
        [['#1d2340', '#6b5aa6', '#ffd58a'], ['#fef3c7', '#fce7f3', '#ede9fe', '#dcfce7']],
        [['#0f2a2e', '#2f7d6d', '#b9f3d0'], ['#dcfce7', '#e0f2fe', '#fef9c3', '#f1f5f9']],
        [['#2b1630', '#b0466e', '#ffc7a8'], ['#ffe4e6', '#fef3c7', '#fae8ff', '#e0e7ff']],
        [['#101c33', '#2f6fc7', '#9ad0ff'], ['#e0f2fe', '#ede9fe', '#dcfce7', '#fef3c7']],
        [['#2a1a0e', '#c26a2c', '#ffd9a0'], ['#ffedd5', '#fef9c3', '#fee2e2', '#ecfccb']],
    ];

    /** Palavras genéricas: só puxam o módulo se o tema não tiver outra palavra conhecida. */
    private const FRACAS = ['aprender', 'curso', 'rotina', 'foco', 'bem', 'trabalho', 'app', 'site', 'saude', 'comida'];

    private const PADROES = ['bokeh', 'caderno', 'estrelas', 'grade', 'ondas', 'pontos'];

    private static function normalizar(string $s): string
    {
        $s = mb_strtolower($s);
        $s = strtr($s, ['á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ç' => 'c']);

        return trim(preg_replace('/[^a-z0-9]+/', ' ', $s));
    }

    /** Módulos que combinam com o tema, na ordem em que aparecem (no máximo 3). */
    public static function modulosDe(string $tema): array
    {
        $palavras = explode(' ', self::normalizar($tema));
        $achados = $fracos = [];
        foreach (self::modulos($tema) as $id => [$chaves]) {
            foreach ($palavras as $i => $p) {
                if ($p === '' || ! in_array($p, $chaves, true)) continue;
                if (in_array($p, self::FRACAS, true)) $fracos[$id] = $fracos[$id] ?? $i;
                else $achados[$id] = $achados[$id] ?? $i;
            }
        }
        // palavras genéricas ("aprender violão") só valem quando nada mais específico combinou
        if (! $achados) $achados = $fracos;
        asort($achados);

        return array_slice(array_keys($achados), 0, 3);
    }

    /** Tema montado no formato de WorkflowsProntos::temas() (sem as imagens). */
    public static function montar(string $tema, array $imagens = []): array
    {
        $tema = trim(preg_replace('/\s+/', ' ', $tema));
        $mods = self::modulosDe($tema);
        $todos = self::modulos($tema);
        $semente = crc32(self::normalizar($tema));
        [$cores, $cartoes] = self::PALETAS[$semente % count(self::PALETAS)];

        // visão geral: meta, próximos passos, imagens e créditos
        $geral = [
            ['texto', '🎯 ' . mb_substr($tema, 0, 60), ['Objetivo:', '', 'Por que isso importa para mim:', '- ', '', 'Como vou saber que deu certo:', '- ']],
            ['lista', '👣 Próximos passos', ['Definir a primeira meta', 'Separar um horário fixo na semana', 'Revisar o progresso no domingo']],
        ];
        foreach ($imagens as $i => $img) {
            $geral[] = ['imagem', '🖼️ Inspiração ' . ($i + 1), $img['src'], ImagensLivres::credito($img)];
        }
        if ($imagens) {
            $geral[] = ['texto', '📷 Créditos das imagens', array_map(fn ($img) => '- ' . ImagensLivres::credito($img), $imagens)];
        }

        $abas = [['Visão geral', 'ideia', $geral]];
        foreach ($mods as $m) $abas[] = $todos[$m][2];
        if (! $mods) {
            // tema sem palavra conhecida: planejamento genérico com o nome do tema
            $abas[] = ['Planejamento', 'calendario', [
                ['tabela', '🗓️ Plano da semana', ['Dia', 'O que fazer', 'Feito'], [['Seg', '', ''], ['Qua', '', ''], ['Sex', '', '']]],
                ['lista', '✅ Tarefas', ['Primeira tarefa sobre ' . mb_substr($tema, 0, 40), 'Pesquisar referências']],
            ]];
            $abas[] = ['Ideias e recursos', 'ideia', [
                ['texto', '💡 Ideias', ['- ', '- ', '- ']],
                ['lista', '🔗 Links e materiais', ['Material 1', 'Material 2']],
            ]];
        }

        return [
            'nome' => mb_substr($tema, 0, 40), 'identidade' => 'Criado por tema', 'frase' => $tema, 'emoji' => '✨',
            'cores' => $cores, 'padrao' => self::PADROES[$semente % count(self::PADROES)], 'escurecer' => 35,
            'musica' => 'lTRiuFIWV54', 'cartoes' => $cartoes, 'abas' => $abas,
        ];
    }

    /** Termo de busca das imagens: o do primeiro módulo, ou o próprio tema. */
    public static function busca(string $tema): string
    {
        $mods = self::modulosDe($tema);

        return $mods ? self::modulos($tema)[$mods[0]][1] : $tema;
    }

    public static function gerar(Request $request, User $u, string $tema, bool $comImagens = true): array
    {
        $tema = trim(preg_replace('/\s+/', ' ', $tema));
        if (mb_strlen($tema) < 3) {
            throw ValidationException::withMessages(['tema' => 'Escreva um tema com pelo menos 3 letras.']);
        }
        $imagens = $comImagens ? ImagensLivres::buscar(self::busca($tema), 2) : [];
        $t = self::montar($tema, $imagens);
        $fundo = $imagens[0]['src'] ?? FundoTema::gerar($t['cores'], $t['padrao'], crc32($tema));

        return WorkflowsProntos::criarDeTema($request, $u, $t, $fundo) + ['imagens' => count($imagens)];
    }
}
