<?php

namespace App\Support;

use App\Models\CorpoDado;
use App\Models\DiversoDado;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Workflows prontos (botão "Workflows prontos" no painel): em um clique cria uma área nova (AreasConta::criarExtra)
 * com abas, cartões iniciais coerentes com o tema e o ambiente de cada aba (fundo desenhado pelo FundoTema, cor e
 * música do YouTube, ver App\Support\Ambiente), no estilo de um mural do Pinterest: cartões coloridos em colunas
 * de alturas diferentes.
 *
 * As abas moram no estado do Diverso com "grupo" = id da área criada (o mesmo formato do diverso.js, v2), então
 * exportar, compartilhar, mover cartões e mudar o fundo continuam valendo como em qualquer área criada.
 * Os temas inspirados em jogos usam só nomes e ideias genéricas: nada de arte, logos ou personagens oficiais.
 */
class WorkflowsProntos
{
    /** Largura dos cartões e espaço entre eles (o quadro do Diverso tem 1088 de largura útil). */
    private const COL = 340;

    private const GAP = 16;

    private const COLUNAS = 3;

    /**
     * Temas. Cada um: nome da área, frase, emoji (só na galeria), três cores do fundo, padrão do fundo,
     * escurecer (0-90), id do vídeo do YouTube (rádios ao vivo de lo-fi/synthwave/jazz conferidas), cores dos
     * cartões e abas => [nome, ícone (WF_ICONES do Diverso), cartões].
     * Cartão: ['texto', título, linhas] | ['lista', título, itens] | ['tabela', título, cabeçalho, linhas].
     */
    public static function temas(): array
    {
        return [
            'estudos' => [
                'nome' => 'Estudos', 'frase' => 'Cronograma, revisões e foco com lo-fi.', 'emoji' => '📚',
                'cores' => ['#fde68a', '#fca5a5', '#c4b5fd'], 'padrao' => 'caderno', 'escurecer' => 40, 'musica' => 'lTRiuFIWV54',
                'cartoes' => ['#fef3c7', '#fce7f3', '#ede9fe', '#dcfce7'],
                'abas' => [
                    ['Planejamento', 'calendario', [
                        ['texto', '🎯 Meta do semestre', ['Escreva aqui o objetivo principal.', '', 'Por que isso importa para mim:', '- ']],
                        ['tabela', '🗓️ Cronograma da semana', ['Dia', 'Matéria', 'Tempo'], [['Seg', '', '1h30'], ['Ter', '', '1h30'], ['Qua', '', '1h30'], ['Qui', '', '1h30'], ['Sex', 'Revisão', '1h']]],
                        ['lista', '✅ Tarefas de hoje', ['Revisar anotações da aula', 'Fazer 10 exercícios', 'Ler 1 capítulo']],
                        ['texto', '🍅 Pomodoro', ['25 min de foco', '5 min de pausa', 'A cada 4 ciclos: pausa de 20 min', '', 'Ciclos hoje: ']],
                    ]],
                    ['Revisões', 'estudo', [
                        ['tabela', '🔁 Revisão espaçada', ['Conteúdo', '1 dia', '7 dias', '30 dias'], [['', '', '', ''], ['', '', '', ''], ['', '', '', '']]],
                        ['lista', '❓ Dúvidas para tirar', ['']],
                        ['texto', '🧠 Resumo em 5 linhas', ['1. ', '2. ', '3. ', '4. ', '5. ']],
                    ]],
                    ['Provas', 'check', [
                        ['tabela', '📅 Datas de provas e entregas', ['Data', 'Matéria', 'O que cai'], [['', '', ''], ['', '', '']]],
                        ['lista', '📝 Checklist do dia da prova', ['Documento e caneta', 'Dormir 8h', 'Revisão leve de manhã', 'Água e lanche']],
                    ]],
                ],
            ],
            'pokemon' => [
                'nome' => 'Jornada Pokémon', 'frase' => 'Time, capturas e jornada de treinador (tema de fã).', 'emoji' => '⚡',
                'cores' => ['#fef08a', '#fb7185', '#60a5fa'], 'padrao' => 'estrelas', 'escurecer' => 35, 'musica' => '4xDzrJKXOOY',
                'cartoes' => ['#fef9c3', '#fee2e2', '#dbeafe', '#dcfce7'],
                'abas' => [
                    ['Meu time', 'estrela', [
                        ['tabela', '⚔️ Time principal', ['Parceiro', 'Tipo', 'Nível', 'Golpes'], [['', '', '', ''], ['', '', '', ''], ['', '', '', ''], ['', '', '', ''], ['', '', '', ''], ['', '', '', '']]],
                        ['texto', '🧭 Estratégia', ['Quem abre a batalha:', 'Quem segura dano:', 'Plano B:']],
                        ['lista', '🎒 Mochila', ['Poções', 'Itens de cura de status', 'Itens de captura']],
                    ]],
                    ['Capturas', 'check', [
                        ['tabela', '📖 Registro de capturas', ['#', 'Nome', 'Onde encontrei', 'Shiny?'], [['001', '', '', ''], ['002', '', '', ''], ['003', '', '', '']]],
                        ['lista', '🎯 Quero capturar', ['']],
                    ]],
                    ['Jornada', 'viagem', [
                        ['lista', '🏅 Insígnias', ['Insígnia 1', 'Insígnia 2', 'Insígnia 3', 'Insígnia 4', 'Insígnia 5', 'Insígnia 6', 'Insígnia 7', 'Insígnia 8']],
                        ['texto', '🗺️ Diário de viagem', ['Rota de hoje:', 'O que aconteceu:', 'Próximo destino:']],
                        ['tabela', '🧪 Tabela de fraquezas (anotações)', ['Tipo', 'Forte contra', 'Fraco contra'], [['', '', ''], ['', '', ''], ['', '', '']]],
                    ]],
                ],
            ],
            'gaming' => [
                'nome' => 'Gaming', 'frase' => 'Backlog, metas, builds e lives.', 'emoji' => '🎮',
                'cores' => ['#1e1b4b', '#7c3aed', '#ec4899'], 'padrao' => 'grade', 'escurecer' => 30, 'musica' => '4xDzrJKXOOY',
                'cartoes' => ['#ede9fe', '#fce7f3', '#e0f2fe', '#fef3c7'],
                'abas' => [
                    ['Backlog', 'jogo', [
                        ['tabela', '🕹️ Backlog', ['Jogo', 'Plataforma', 'Status', 'Nota'], [['', '', 'Jogando', ''], ['', '', 'Na fila', ''], ['', '', 'Zerado', '']]],
                        ['lista', '🏆 Conquistas que quero', ['']],
                        ['texto', '⭐ Jogo do mês', ['Nome:', 'Por que estou jogando:', 'Impressões:']],
                    ]],
                    ['Ranked', 'grafico', [
                        ['tabela', '📈 Evolução', ['Data', 'Rank', 'Vitórias', 'Derrotas'], [['', '', '', ''], ['', '', '', '']]],
                        ['texto', '🧩 Build / loadout', ['Personagem / classe:', 'Itens:', 'Por que funciona:']],
                        ['lista', '🎯 Treino da semana', ['Aquecimento 15 min', 'Revisar replay de uma derrota', 'Treinar mira / mecânica']],
                    ]],
                    ['Lives', 'chat', [
                        ['tabela', '📺 Agenda de lives', ['Dia', 'Horário', 'Jogo'], [['', '', ''], ['', '', '']]],
                        ['lista', '💡 Ideias de conteúdo', ['Desafio da comunidade', 'Melhores momentos da semana']],
                    ]],
                ],
            ],
            'negocios' => [
                'nome' => 'Negócios', 'frase' => 'Metas, funil de vendas e rotina do negócio.', 'emoji' => '💼',
                'cores' => ['#0f172a', '#1e3a8a', '#0d9488'], 'padrao' => 'pontos', 'escurecer' => 35, 'musica' => '5yx6BWlEVcY',
                'cartoes' => ['#e0f2fe', '#dcfce7', '#f1f5f9', '#fef3c7'],
                'abas' => [
                    ['Visão', 'ideia', [
                        ['texto', '🧭 Visão e proposta de valor', ['Para quem:', 'Problema que resolvo:', 'Por que eu:']],
                        ['tabela', '🎯 Metas do trimestre (OKR)', ['Objetivo', 'Resultado-chave', 'Progresso'], [['', '', '0%'], ['', '', '0%'], ['', '', '0%']]],
                    ]],
                    ['Vendas', 'dinheiro', [
                        ['tabela', '🧲 Funil', ['Cliente', 'Etapa', 'Valor', 'Próximo passo'], [['', 'Contato', '', ''], ['', 'Proposta', '', ''], ['', 'Fechado', '', '']]],
                        ['lista', '📞 Follow-ups', ['']],
                        ['texto', '💬 Roteiro de abordagem', ['Abertura:', 'Pergunta-chave:', 'Oferta:', 'Chamada para ação:']],
                    ]],
                    ['Rotina', 'trabalho', [
                        ['lista', '☀️ Rotina do CEO', ['Revisar caixa', 'Responder clientes', '1 tarefa de crescimento', 'Planejar amanhã']],
                        ['tabela', '💰 Fluxo de caixa', ['Data', 'Entrada', 'Saída', 'Saldo'], [['', '', '', ''], ['', '', '', '']]],
                        ['texto', '📝 Ata da reunião', ['Data:', 'Decisões:', 'Responsáveis:']],
                    ]],
                ],
            ],
            'musica' => [
                'nome' => 'Music production', 'frase' => 'Projetos, sessões de estúdio e lançamentos.', 'emoji' => '🎛️',
                'cores' => ['#111827', '#be185d', '#f59e0b'], 'padrao' => 'ondas', 'escurecer' => 30, 'musica' => '5yx6BWlEVcY',
                'cartoes' => ['#fce7f3', '#fef3c7', '#ede9fe', '#e0f2fe'],
                'abas' => [
                    ['Projetos', 'musica', [
                        ['tabela', '🎚️ Faixas em andamento', ['Faixa', 'BPM', 'Tom', 'Etapa'], [['', '', '', 'Ideia'], ['', '', '', 'Arranjo'], ['', '', '', 'Mix']]],
                        ['lista', '🎹 Ideias de samples e timbres', ['']],
                        ['texto', '🔊 Referências', ['Faixa de referência:', 'O que eu gosto nela:', 'Volume de referência (LUFS):']],
                    ]],
                    ['Estúdio', 'relogio', [
                        ['lista', '✅ Checklist de mixagem', ['Organizar e nomear tracks', 'Gain staging', 'EQ subtrativa', 'Compressão', 'Espacialidade (reverb/delay)', 'Ouvir em fones e caixas']],
                        ['lista', '🎧 Checklist de master', ['Limiter no fim da cadeia', 'Conferir LUFS e true peak', 'Exportar WAV 24 bits']],
                        ['texto', '📓 Diário de sessão', ['Hoje eu:', 'Aprendi:', 'Amanhã:']],
                    ]],
                    ['Lançamento', 'estrela', [
                        ['tabela', '🚀 Plano de lançamento', ['Data', 'Ação', 'Feito?'], [['', 'Enviar para a distribuidora', ''], ['', 'Capa pronta', ''], ['', 'Teaser nas redes', ''], ['', 'Pitch para playlists', '']]],
                        ['lista', '📣 Divulgação', ['Vídeo curto do processo', 'Story com prévia']],
                    ]],
                ],
            ],
            'leitura' => [
                'nome' => 'Leitura', 'frase' => 'Estante, metas e citações favoritas.', 'emoji' => '📖',
                'cores' => ['#78350f', '#d97706', '#fde68a'], 'padrao' => 'bokeh', 'escurecer' => 45, 'musica' => 'DWcJFNfaw9c',
                'cartoes' => ['#fef3c7', '#ffedd5', '#fae8ff', '#ecfccb'],
                'abas' => [
                    ['Estante', 'livro', [
                        ['tabela', '📚 Lendo agora / quero ler', ['Livro', 'Autor(a)', 'Status', 'Nota'], [['', '', 'Lendo', ''], ['', '', 'Quero ler', ''], ['', '', 'Lido', '']]],
                        ['texto', '🎯 Meta do ano', ['Livros no ano: 0 / 12', 'Páginas por dia: 20']],
                        ['lista', '🛒 Lista de desejos', ['']],
                    ]],
                    ['Anotações', 'ideia', [
                        ['texto', '✨ Citações favoritas', ['"" — ']],
                        ['texto', '🧠 Resenha rápida', ['Livro:', 'Em uma frase:', 'O que levo comigo:']],
                        ['lista', '📌 Clube do livro', ['Escolher o próximo livro', 'Marcar o encontro']],
                    ]],
                ],
            ],
            'fitness' => [
                'nome' => 'Fitness', 'frase' => 'Treinos, cargas, hábitos e progresso.', 'emoji' => '💪',
                'cores' => ['#052e16', '#16a34a', '#a3e635'], 'padrao' => 'grade', 'escurecer' => 35, 'musica' => '4xDzrJKXOOY',
                'cartoes' => ['#dcfce7', '#ecfccb', '#e0f2fe', '#fef3c7'],
                'abas' => [
                    ['Treinos', 'saude', [
                        ['tabela', '🏋️ Treino A / B / C', ['Dia', 'Grupo', 'Exercícios'], [['Seg', 'Peito e tríceps', ''], ['Qua', 'Costas e bíceps', ''], ['Sex', 'Pernas e ombros', '']]],
                        ['tabela', '📈 Cargas', ['Exercício', 'Semana 1', 'Semana 2', 'Semana 3'], [['', '', '', ''], ['', '', '', ''], ['', '', '', '']]],
                        ['lista', '🔥 Aquecimento', ['5 min de cardio leve', 'Mobilidade de ombro e quadril', 'Série leve do 1º exercício']],
                    ]],
                    ['Hábitos', 'check', [
                        ['lista', '💧 Hábitos do dia', ['2 litros de água', 'Proteína em todas as refeições', 'Dormir 7h+', '8 mil passos']],
                        ['tabela', '📏 Medidas', ['Data', 'Peso', 'Cintura', 'Obs.'], [['', '', '', ''], ['', '', '', '']]],
                        ['texto', '🎯 Meta', ['Quero chegar em:', 'Até:', 'Por quê:']],
                    ]],
                ],
            ],
            'viagem' => [
                'nome' => 'Viagem', 'frase' => 'Roteiro, orçamento e mala pronta.', 'emoji' => '✈️',
                'cores' => ['#0ea5e9', '#22d3ee', '#fde68a'], 'padrao' => 'bokeh', 'escurecer' => 40, 'musica' => '7NOSDKb0HlU',
                'cartoes' => ['#e0f2fe', '#fef3c7', '#ccfbf1', '#fce7f3'],
                'abas' => [
                    ['Roteiro', 'viagem', [
                        ['tabela', '🗺️ Roteiro dia a dia', ['Dia', 'Cidade', 'Programa'], [['1', '', ''], ['2', '', ''], ['3', '', '']]],
                        ['lista', '📍 Lugares imperdíveis', ['']],
                        ['texto', '🏨 Hospedagem e transporte', ['Hotel:', 'Check-in:', 'Voo / ônibus:', 'Reserva nº:']],
                    ]],
                    ['Orçamento', 'dinheiro', [
                        ['tabela', '💸 Gastos', ['Item', 'Previsto', 'Gasto'], [['Passagens', '', ''], ['Hospedagem', '', ''], ['Comida', '', ''], ['Passeios', '', '']]],
                        ['lista', '🧳 Mala', ['Documentos', 'Carregadores', 'Remédios', 'Roupas para 1 dia a mais']],
                        ['lista', '✅ Antes de sair', ['Seguro viagem', 'Avisar o banco', 'Baixar mapas offline']],
                    ]],
                ],
            ],
            'escrita' => [
                'nome' => 'Escrita e worldbuilding', 'frase' => 'Mundo, personagens, enredo e metas de escrita.', 'emoji' => '🖋️',
                'cores' => ['#1e1b4b', '#4338ca', '#a78bfa'], 'padrao' => 'estrelas', 'escurecer' => 40, 'musica' => 'DWcJFNfaw9c',
                'cartoes' => ['#ede9fe', '#e0e7ff', '#fae8ff', '#fef3c7'],
                'abas' => [
                    ['Mundo', 'globo', [
                        ['texto', '🌍 O mundo', ['Nome:', 'Clima e geografia:', 'Regra que torna este mundo único:']],
                        ['tabela', '🏰 Lugares', ['Lugar', 'Quem manda', 'Segredo'], [['', '', ''], ['', '', '']]],
                        ['texto', '✨ Sistema de magia / tecnologia', ['Como funciona:', 'Custo:', 'Limites:']],
                    ]],
                    ['Personagens', 'usuario', [
                        ['tabela', '🧑‍🤝‍🧑 Elenco', ['Nome', 'Quer', 'Teme', 'Arco'], [['', '', '', ''], ['', '', '', ''], ['', '', '', '']]],
                        ['texto', '🎭 Ficha do protagonista', ['Nome:', 'Ferida do passado:', 'Mentira em que acredita:', 'Verdade que vai aprender:']],
                    ]],
                    ['Enredo', 'livro', [
                        ['lista', '📐 Estrutura em 3 atos', ['Ato 1: mundo comum e chamado', 'Ato 2: provações e ponto sem volta', 'Ato 3: clímax e resolução']],
                        ['tabela', '✍️ Meta de palavras', ['Dia', 'Palavras', 'Total'], [['', '', ''], ['', '', '']]],
                        ['lista', '💡 Ideias soltas', ['']],
                    ]],
                ],
            ],
        ];
    }

    /** Resumo para a galeria (sem as abas inteiras). */
    public static function galeria(): array
    {
        $saida = [];
        foreach (self::temas() as $id => $t) {
            $saida[] = [
                'id' => $id, 'nome' => $t['nome'], 'frase' => $t['frase'], 'emoji' => $t['emoji'], 'cores' => $t['cores'],
                'abas' => array_map(fn ($a) => $a[0], $t['abas']),
                'cartoes' => array_sum(array_map(fn ($a) => count($a[2]), $t['abas'])),
            ];
        }

        return $saida;
    }

    /**
     * Cria o workflow do tema para a pessoa. Devolve a área criada (id, nome, url).
     * Lança ValidationException se o tema não existe, se a conta chegou ao limite de áreas ou se o Diverso ainda
     * está no formato antigo.
     */
    public static function criar(Request $request, User $u, string $tema): array
    {
        $t = self::temas()[$tema] ?? null;
        if (! $t) {
            throw ValidationException::withMessages(['tema' => 'Tema desconhecido.']);
        }

        $valor = DiversoDado::where('user_id', $u->id)->where('chave', 'estado')->value('valor');
        $e = AbasRetiradas::decodificar($valor) ?? new \stdClass;
        if ($valor !== null && ($e->v ?? null) !== 2) {
            throw ValidationException::withMessages(['tema' => 'Abra o Diverso uma vez antes (ele atualiza o formato das abas) e tente de novo.']);
        }
        $e->v = 2;
        $e->abas = is_array($e->abas ?? null) ? $e->abas : [];
        $e->blocos = is_array($e->blocos ?? null) ? $e->blocos : [];
        $e->layout = isset($e->layout) && is_object($e->layout) ? $e->layout : new \stdClass;

        // nome livre: "Estudos", "Estudos 2"...
        $nomes = array_column(AreasConta::config($u)['extras'], 'nome');
        $nome = $t['nome'];
        for ($i = 2; in_array($nome, $nomes, true); $i++) {
            $nome = $t['nome'] . ' ' . $i;
        }

        return DB::transaction(function () use ($request, $u, $t, $tema, $e, $nome) {
            $area = AreasConta::criarExtra($u, $nome);
            $fundo = FundoTema::gerar($t['cores'], $t['padrao'], crc32($tema));
            $usados = [];
            $setores = [];

            foreach ($t['abas'] as $n => [$abaNome, $icone, $cartoes]) {
                $abaId = self::novoId('a', $usados);
                $e->abas[] = (object) ['id' => $abaId, 'nome' => $abaNome, 'icone' => $icone, 'grupo' => $area['id']];
                $lay = new \stdClass;
                $alturas = array_fill(0, self::COLUNAS, 0);
                $z = 1;
                foreach ($cartoes as $i => $c) {
                    [$tipo, $titulo] = $c;
                    $dados = match ($tipo) {
                        'texto'  => ['texto' => implode("\n", $c[2]), 'tam' => 'm'],
                        'lista'  => ['itens' => array_values(array_map(fn ($it) => ['id' => self::uid(), 't' => $it, 'f' => false], array_filter($c[2], fn ($it) => $it !== '')))],
                        'tabela' => ['cab' => $c[2], 'linhas' => $c[3]],
                    };
                    $h = self::altura($tipo, $c);
                    $bid = self::novoId('b', $usados);
                    $e->blocos[] = (object) ['id' => $bid, 'secao' => $abaId, 'tipo' => $tipo, 'titulo' => $titulo, 'links' => [], 'dados' => $dados];
                    // mural: cada cartão entra na coluna mais baixa (alturas diferentes, como no Pinterest)
                    $col = array_keys($alturas, min($alturas))[0];
                    $lay->$bid = (object) [
                        'x' => $col * (self::COL + self::GAP), 'y' => $alturas[$col], 'w' => self::COL, 'h' => $h, 'z' => $z++,
                        'cor' => $t['cartoes'][($i + $n) % count($t['cartoes'])],
                    ];
                    $alturas[$col] += $h + self::GAP;
                }
                $e->layout->$abaId = $lay;
                $setores[] = $abaId;
            }

            DiversoDado::updateOrCreate(['user_id' => $u->id, 'chave' => 'estado'], ['valor' => AbasRetiradas::codificar($e)]);

            // ambiente de cada aba: fundo do tema e a rádio do YouTube
            $livres = 60 - CorpoDado::where('user_id', $u->id)->where('chave', 'like', 'amb.diverso.s.%')->where('chave', 'not like', '%.img')->count();
            foreach (array_slice($setores, 0, max(0, $livres)) as $abaId) {
                Ambiente::gravar($u->id, 'diverso', $abaId, [
                    'fundo'  => ['proprio' => true, 'cor' => $t['cores'][1], 'ajuste' => 'cover', 'escurecer' => $t['escurecer'], 'desfoque' => 0],
                    'musica' => ['tipo' => 'youtube', 'youtube' => $t['musica'], 'nome' => null, 'volume' => 35, 'auto' => false],
                ]);
                if ($fundo) {
                    CorpoDado::updateOrCreate(['user_id' => $u->id, 'chave' => Ambiente::chave('diverso', $abaId) . '.img'], ['valor' => $fundo]);
                }
            }

            Sincronia::avisarMudanca($request, 'diverso', $u->id, AbasRetiradas::codificar($e));

            return $area + ['url' => route('areas.extra', $area['id'])];
        });
    }

    /** Altura do cartão pelo conteúdo (o cabeçalho do cartão tem ~46px). */
    private static function altura(string $tipo, array $c): int
    {
        return match ($tipo) {
            'texto'  => max(180, 96 + 26 * count($c[2])),
            'lista'  => max(200, 120 + 34 * count(array_filter($c[2], fn ($it) => $it !== ''))),
            'tabela' => max(200, 110 + 38 * (count($c[3]) + 1)),
        };
    }

    private static function uid(): string
    {
        return strtolower(base_convert((string) (int) (microtime(true) * 1000), 10, 36)) . bin2hex(random_bytes(3));
    }

    private static function novoId(string $prefixo, array &$usados): string
    {
        do {
            $id = $prefixo . self::uid();
        } while (isset($usados[$id]));
        $usados[$id] = true;

        return $id;
    }
}
