<?php

/** Funções comuns dos scripts que montam os arquivos de questões (construir.php e construir-enem-2009-2017.php). */

function limpar(string $s): string
{
    $s = str_replace(["\r", "\u{200a}", "\u{00a0}"], ['', ' ', ' '], $s);
    $s = preg_replace('/[ \t]+/', ' ', $s);
    $s = preg_replace("/ *\n */", "\n", $s);

    return trim(preg_replace("/\n{3,}/", "\n\n", $s));
}

/** Junta as linhas quebradas do texto (parágrafos separados por linha em branco). */
function juntar(array $linhas): string
{
    $pars = [];
    $atual = [];
    foreach ($linhas as $l) {
        if (trim($l) === '') {
            if ($atual) { $pars[] = implode(' ', $atual); $atual = []; }
            continue;
        }
        $atual[] = trim($l);
    }
    if ($atual) $pars[] = implode(' ', $atual);

    return limpar(implode("\n", $pars));
}

function palavras(string $s): array
{
    $s = mb_strtolower($s);
    $s = strtr($s, ['á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ç' => 'c', 'ü' => 'u']);
    preg_match_all('/[a-z]{4,}/', $s, $m);

    return $m[0];
}

/* ---------- assunto por palavras-chave ---------- */
const ASSUNTOS = [
    'Ética Profissional' => ['Honorários' => 'honor[áa]ri', 'Sociedade de advogados' => 'sociedade de advogados', 'Infrações e sanções' => 'infra[çc][ãa]o|san[çc][ãa]o|suspens|exclus|censura|processo disciplinar', 'Inscrição na OAB' => 'inscri[çc][ãa]o|estagi', 'Sigilo e publicidade' => 'sigilo|publicidade|an[úu]ncio', 'Mandato e procuração' => 'mandato|procura[çc][ãa]o|substabelec|ren[úu]ncia'],
    'Direito Constitucional' => ['Controle de constitucionalidade' => 'inconstitucional|controle de constitucionalidade|adi\b|adpf|a[çc][ãa]o direta', 'Direitos fundamentais' => 'direitos? fundamenta|habeas|mandado de seguran|liberdade de', 'Poder Legislativo' => 'congresso|senado|c[âa]mara dos deputados|processo legislativo|emenda|medida provis', 'Poder Executivo' => 'presidente da rep[úu]blica|governador|prefeito|ministro de estado', 'Poder Judiciário' => 'supremo tribunal|stf|stj|poder judici|magistrad', 'Organização do Estado' => 'munic[íi]pio|estados-membros|uni[ãa]o federal|compet[êe]ncia (legislativa|comum|privativa)|interven[çc][ãa]o federal'],
    'Direito Civil' => ['Contratos' => 'contrat|compra e venda|loca[çc][ãa]o|doa[çc][ãa]o|fian[çc]a', 'Família' => 'casamento|div[óo]rcio|uni[ãa]o est[áa]vel|alimentos|guarda|filia[çc][ãa]o', 'Sucessões' => 'heran[çc]a|herdeir|testament|sucess[ãa]o|invent[áa]rio', 'Responsabilidade civil' => 'responsabilidade civil|indeniza|dano moral', 'Direitos reais' => 'propriedade|posse|usucapi|hipoteca|servid[ãa]o|condom[íi]nio', 'Obrigações' => 'obriga[çc][ãa]o|credor|devedor|pagamento|juros', 'Parte geral' => 'prescri[çc][ãa]o|decad[êe]ncia|capacidade|neg[óo]cio jur[íi]dico|pessoa jur[íi]dica'],
    'Direito Penal' => ['Crimes contra a pessoa' => 'homic[íi]dio|les[ãa]o corporal|estupro|feminic', 'Crimes contra o patrimônio' => 'furto|roubo|estelionato|extors[ãa]o|recepta[çc]', 'Penas e aplicação' => 'pena |dosimetria|regime (fechado|semiaberto|aberto)|substitui[çc][ãa]o da pena|sursis', 'Teoria do crime' => 'dolo|culpa|tentativa|leg[íi]tima defesa|estado de necessidade|erro de', 'Extinção da punibilidade' => 'prescri[çc][ãa]o|extin[çc][ãa]o da punibilidade|decad[êe]ncia'],
    'Direito Processual Penal' => ['Prisão e liberdade' => 'pris[ãa]o|flagrante|fian[çc]a|preventiva|tempor[áa]ria|liberdade provis', 'Recursos' => 'recurso|apela[çc][ãa]o|habeas corpus|revis[ãa]o criminal|embargos', 'Provas' => 'prova|testemunha|per[íi]cia|interrogat', 'Ação penal' => 'a[çc][ãa]o penal|den[úu]ncia|queixa|inqu[ée]rito', 'Júri' => 'j[úu]ri|jurados|pron[úu]ncia'],
    'Direito Processual Civil' => ['Recursos' => 'recurso|apela[çc][ãa]o|agravo|embargos de declara|recurso especial', 'Execução' => 'execu[çc][ãa]o|cumprimento de senten|penhora', 'Tutelas provisórias' => 'tutela|liminar|urg[êe]ncia', 'Procedimentos especiais' => 'possess[óo]ri|monit[óo]ri|invent[áa]rio|usucapi', 'Petição e resposta' => 'peti[çc][ãa]o inicial|contesta[çc][ãa]o|reconven[çc][ãa]o|revelia|cita[çc][ãa]o'],
    'Direito do Trabalho' => ['Jornada e férias' => 'jornada|horas? extra|f[ée]rias|intervalo|descanso', 'Rescisão' => 'rescis[ãa]o|dispensa|justa causa|aviso pr[ée]vio|fgts', 'Salário e remuneração' => 'sal[áa]rio|remunera[çc][ãa]o|gorjeta|adicional', 'Estabilidade' => 'estabilidade|gestante|cipa|acidente de trabalho'],
    'Direito Processual do Trabalho' => ['Recursos' => 'recurso|agravo|embargos', 'Execução' => 'execu[çc][ãa]o|penhora', 'Audiência e provas' => 'audi[êe]ncia|prova|testemunha|revelia'],
    'Direito Tributário' => ['Impostos' => 'icms|ipi|iptu|ipva|itbi|itcmd|imposto de renda|iss\b|imposto', 'Crédito tributário' => 'cr[ée]dito tribut[áa]rio|lan[çc]amento|prescri[çc][ãa]o|decad[êe]ncia|execu[çc][ãa]o fiscal', 'Limitações ao poder de tributar' => 'imunidade|anterioridade|legalidade tribut|isen[çc][ãa]o', 'Taxas e contribuições' => 'taxa|contribui[çc][ãa]o de melhoria|contribui[çc][õo]es'],
    'Direito Administrativo' => ['Licitações e contratos' => 'licita[çc][ãa]o|preg[ãa]o|contrato administrativo|concess[ãa]o|permiss[ãa]o', 'Servidores públicos' => 'servidor|cargo p[úu]blico|concurso p[úu]blico|est[áa]gio probat|aposentadoria', 'Atos administrativos' => 'ato administrativo|anula[çc][ãa]o|revoga[çc][ãa]o|poder de pol[íi]cia', 'Improbidade' => 'improbidade', 'Responsabilidade do Estado' => 'responsabilidade (civil )?do estado|responsabilidade objetiva', 'Bens públicos e intervenção' => 'desapropria|tombamento|bens p[úu]blicos|servid[ãa]o administrativa'],
    'Direito Empresarial' => ['Sociedades' => 'sociedade|s[óo]cio|acionista|companhia|ltda', 'Títulos de crédito' => 'cheque|nota promiss[óo]ria|duplicata|letra de c[âa]mbio|t[íi]tulo de cr[ée]dito|aval|endosso', 'Falência e recuperação' => 'fal[êe]ncia|recupera[çc][ãa]o judicial|recupera[çc][ãa]o extrajudicial', 'Propriedade industrial' => 'marca|patente|propriedade industrial'],
    'Matemática' => ['Porcentagem e juros' => 'porcent|%|juros|desconto|lucro', 'Probabilidade' => 'probabilidade|chance|sorte', 'Estatística' => 'm[ée]dia|mediana|moda|desvio|gr[áa]fico|tabela', 'Geometria' => '[áa]rea|volume|tri[âa]ngulo|c[íi]rculo|cilindro|cubo|[âa]ngulo|per[íi]metro|esfera|cone|pir[âa]mide', 'Funções' => 'fun[çc][ãa]o|par[áa]bola|exponencial|logaritm', 'Razão e proporção' => 'raz[ãa]o|propor[çc]|escala|regra de tr[êe]s', 'Contagem' => 'combina[çc]|arranjo|permuta|quantas maneiras|quantidade de modos'],
    'Ciências da Natureza' => ['Biologia' => 'c[ée]lula|gen[ée]tic|dna|esp[ée]cie|organismo|ecossistema|v[íi]rus|bact[ée]ria|prote[íi]na|evolu[çc]|plantas?|animal|doen[çc]a|sangue', 'Química' => 'qu[íi]mic|rea[çc][ãa]o|mol[ée]cula|[áa]tomo|[íi]ons?|solu[çc][ãa]o|ph\b|[áa]cido|combust[ãa]o|elemento|subst[âa]ncia|composto', 'Física' => 'energia|velocidade|for[çc]a|corrente el[ée]tric|tens[ãa]o|resist[êe]ncia|onda|luz|temperatura|calor|press[ãa]o|massa|acelera'],
    'Ciências Humanas' => ['História' => 's[ée]culo|imp[ée]rio|col[ôo]nia|revolu[çc][ãa]o|guerra|ditadura|rep[úu]blica|escravid|medieval|antig', 'Geografia' => 'clima|relevo|urbaniza|popula[çc][ãa]o|territ[óo]ri|agr[íi]col|bioma|migra[çc]|globaliza|ambient|regi[ãa]o', 'Filosofia' => 'fil[óo]sof|plat[ãa]o|arist[óo]teles|kant|descartes|nietzsche|[ée]tica|raz[ãa]o', 'Sociologia' => 'sociedade|social|trabalho|cultura|movimento|desigualdade|cidadania'],
    'Linguagens' => ['Língua estrangeira' => '\b(the|and|of|is|el|los|las|que es)\b', 'Literatura' => 'poema|poeta|romance|narrador|liter[áa]ri|verso|personagem|conto', 'Artes' => 'arte|pintura|obra|escultura|m[úu]sica|dan[çc]a|teatro|artista', 'Educação física' => 'esporte|atividade f[íi]sica|corpo|exerc[íi]cio|jogo|atleta', 'Gramática e variação' => 'variante|varia[çc][ãa]o lingu|norma-padr[ãa]o|regional|g[íi]ria|uso da l[íi]ngua', 'Gêneros e interpretação' => 'texto|g[êe]nero|leitor|argument|publicidade|campanha|not[íi]cia|reportagem'],
];

function assunto(string $materia, string $texto): string
{
    $melhor = 'Geral';
    $max = 0;
    foreach (ASSUNTOS[$materia] ?? [] as $nome => $re) {
        $n = preg_match_all('/' . $re . '/iu', $texto);
        if ($n > $max) { $max = $n; $melhor = $nome; }
    }

    return $melhor;
}

/** Dificuldade estimada: terços do tamanho do texto (enunciado + alternativas) dentro de cada matéria. */
function dificuldadePorTamanho(array &$saida): void
{
    $porMateria = [];
    foreach ($saida as $i => $q) $porMateria[$q['materia']][$i] = mb_strlen($q['enunciado'] . implode('', $q['alts']));
    foreach ($porMateria as $lista) {
        asort($lista);
        $n = count($lista);
        $k = 0;
        foreach ($lista as $i => $_) {
            $saida[$i]['dificuldade'] = $k < $n / 3 ? 'facil' : ($k < 2 * $n / 3 ? 'media' : 'dificil');
            $k++;
        }
    }
}
