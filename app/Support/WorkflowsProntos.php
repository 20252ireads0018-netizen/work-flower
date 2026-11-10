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
 * com abas, cartões iniciais de um assunto sério (estudos, projetos, negócios...) e uma identidade visual própria:
 * ilustração de fundo exclusiva (resources/workflows-prontos/{tema}.svg → .jpg; sem ela, o FundoTema desenha um
 * degradê), cores dos cartões combinando e a música do YouTube de cada aba (App\Support\Ambiente), no estilo de um
 * mural do Pinterest: cartões coloridos em colunas de alturas diferentes.
 *
 * As abas moram no estado do Diverso com "grupo" = id da área criada (o mesmo formato do diverso.js, v2), então
 * exportar, compartilhar, mover cartões e mudar o fundo continuam valendo como em qualquer área criada.
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
                'nome' => 'Estudos', 'identidade' => 'Estudo noturno lo-fi', 'frase' => 'Cronograma, revisões e foco com lo-fi.', 'emoji' => '📚',
                'cores' => ['#1d2340', '#6b5aa6', '#ffd58a'], 'padrao' => 'caderno', 'escurecer' => 20, 'musica' => 'lTRiuFIWV54',
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
            'projetos' => [
                'nome' => 'Projetos', 'identidade' => 'Onda japonesa', 'frase' => 'Escopo, roadmap, execução e riscos de um projeto.', 'emoji' => '🌊',
                'cores' => ['#efe4cc', '#2c5383', '#c8432f'], 'padrao' => 'ondas', 'escurecer' => 15, 'musica' => '5yx6BWlEVcY',
                'cartoes' => ['#f3ead6', '#dbe6f2', '#f6dcd6', '#e6eef7'],
                'abas' => [
                    ['Visão geral', 'ideia', [
                        ['texto', '🎯 Objetivo do projeto', ['O que vamos entregar:', 'Para quem:', 'Como saberemos que deu certo:']],
                        ['lista', '📦 Escopo (entra)', ['']],
                        ['lista', '🚫 Fora do escopo', ['']],
                        ['tabela', '👥 Pessoas envolvidas', ['Nome', 'Papel', 'Contato'], [['', 'Responsável', ''], ['', 'Aprovação', ''], ['', 'Execução', '']]],
                    ]],
                    ['Roadmap', 'calendario', [
                        ['tabela', '🗺️ Marcos', ['Marco', 'Data', 'Status'], [['Kickoff', '', 'Feito'], ['Primeira entrega', '', 'Em andamento'], ['Entrega final', '', 'A fazer']]],
                        ['texto', '📌 Prioridades do mês', ['1. ', '2. ', '3. ']],
                    ]],
                    ['Execução', 'check', [
                        ['lista', '🧾 Backlog', ['Definir requisitos', 'Montar cronograma', 'Validar com o responsável']],
                        ['tabela', '⚠️ Riscos', ['Risco', 'Impacto', 'Plano'], [['', 'Alto', ''], ['', 'Médio', '']]],
                        ['texto', '📝 Ata da reunião', ['Data:', 'Decisões:', 'Próximos passos:']],
                    ]],
                ],
            ],
            'gamedev' => [
                'nome' => 'Game dev', 'identidade' => 'Arcade synthwave', 'frase' => 'Documento de design, sprints, bugs e playtests.', 'emoji' => '🕹️',
                'cores' => ['#0d0628', '#7a1f6f', '#ff8a5b'], 'padrao' => 'grade', 'escurecer' => 25, 'musica' => '4xDzrJKXOOY',
                'cartoes' => ['#ede9fe', '#fce7f3', '#e0f2fe', '#ffedd5'],
                'abas' => [
                    ['Design', 'ideia', [
                        ['texto', '💡 Conceito', ['Gênero:', 'Em uma frase:', 'Plataformas:', 'Público:']],
                        ['lista', '🏛️ Pilares do jogo', ['', '', '']],
                        ['tabela', '🔁 Loop principal', ['Ação', 'Recompensa', 'Por que volta'], [['', '', ''], ['', '', '']]],
                    ]],
                    ['Produção', 'codigo', [
                        ['lista', '🏃 Sprint atual', ['Protótipo do movimento', 'Primeira fase jogável', 'Menu principal']],
                        ['tabela', '🐞 Bugs', ['Bug', 'Gravidade', 'Status'], [['', 'Alta', 'Aberto'], ['', 'Baixa', 'Aberto']]],
                        ['texto', '🧱 Decisões técnicas', ['Engine:', 'Controle de versão:', 'Padrões do projeto:']],
                    ]],
                    ['Playtests', 'usuario', [
                        ['tabela', '🧪 Rodadas de teste', ['Data', 'Testador', 'O que achou', 'Ação'], [['', '', '', ''], ['', '', '', '']]],
                        ['lista', '🚀 Antes de lançar', ['Página na loja', 'Trailer', 'Build de demonstração', 'Lista de imprensa']],
                    ]],
                ],
            ],
            'negocios' => [
                'nome' => 'Negócios', 'identidade' => 'Art déco dourado', 'frase' => 'Metas, funil de vendas e rotina do negócio.', 'emoji' => '💼',
                'cores' => ['#061a17', '#0b2b26', '#c9a24a'], 'padrao' => 'pontos', 'escurecer' => 20, 'musica' => '5yx6BWlEVcY',
                'cartoes' => ['#f3e7c4', '#dcebe5', '#efe9d8', '#e3efe9'],
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
                'nome' => 'Music production', 'identidade' => 'Estúdio neon', 'frase' => 'Projetos, sessões de estúdio e lançamentos.', 'emoji' => '🎛️',
                'cores' => ['#0e0a14', '#3a0d3c', '#ff8a3d'], 'padrao' => 'ondas', 'escurecer' => 20, 'musica' => '5yx6BWlEVcY',
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
                'nome' => 'Leitura', 'identidade' => 'Outono aconchegante', 'frase' => 'Estante, metas e citações favoritas.', 'emoji' => '📖',
                'cores' => ['#f6d6a8', '#e3995b', '#9c4f2a'], 'padrao' => 'bokeh', 'escurecer' => 20, 'musica' => 'DWcJFNfaw9c',
                'cartoes' => ['#fde6c8', '#f8d6b8', '#f3e3c7', '#fbe9d5'],
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
                'nome' => 'Fitness', 'identidade' => 'Trilha ao amanhecer', 'frase' => 'Treinos, cargas, hábitos e progresso.', 'emoji' => '💪',
                'cores' => ['#ffd9a0', '#5d9471', '#1d4a37'], 'padrao' => 'bokeh', 'escurecer' => 20, 'musica' => '4xDzrJKXOOY',
                'cartoes' => ['#dcefe0', '#fde3cc', '#e6f2df', '#fff1d6'],
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
                'nome' => 'Viagem', 'identidade' => 'Pôster de viagem retrô', 'frase' => 'Roteiro, orçamento e mala pronta.', 'emoji' => '✈️',
                'cores' => ['#fbe3b5', '#f28c4b', '#11707a'], 'padrao' => 'bokeh', 'escurecer' => 20, 'musica' => '7NOSDKb0HlU',
                'cartoes' => ['#d5f3ef', '#fde3c8', '#fff3dc', '#f9d9c9'],
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
                'nome' => 'Escrita e worldbuilding', 'identidade' => 'Fantasia ao luar', 'frase' => 'Mundo, personagens, enredo e metas de escrita.', 'emoji' => '🖋️',
                'cores' => ['#0d0b2b', '#2a1f5f', '#b48cff'], 'padrao' => 'estrelas', 'escurecer' => 20, 'musica' => 'DWcJFNfaw9c',
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
                'id' => $id, 'nome' => $t['nome'], 'identidade' => $t['identidade'], 'frase' => $t['frase'], 'emoji' => $t['emoji'], 'cores' => $t['cores'],
                // miniatura da ilustração (public/img/workflows-prontos/{tema}.jpg)
                'capa' => is_file(public_path("img/workflows-prontos/{$id}.jpg")) ? asset("img/workflows-prontos/{$id}.jpg") : null,
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
            $fundo = self::ilustracao($tema) ?? FundoTema::gerar($t['cores'], $t['padrao'], crc32($tema));
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

    /**
     * Ilustração do tema (identidade visual própria): resources/workflows-prontos/{tema}.jpg, gerada a partir do
     * {tema}.svg da mesma pasta. Volta como data URL JPEG, o formato que o fundo do Ambiente guarda; null se não houver.
     */
    public static function ilustracao(string $tema): ?string
    {
        $arq = resource_path("workflows-prontos/{$tema}.jpg");
        if (! preg_match('/^[a-z]+$/', $tema) || ! is_file($arq)) {
            return null;
        }

        return 'data:image/jpeg;base64,' . base64_encode((string) file_get_contents($arq));
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
