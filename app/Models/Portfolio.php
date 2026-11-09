<?php

namespace App\Models;

use App\Support\Midia;
use App\Support\Planos;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Portfólio profissional de uma conta (Negócios › Portfólio), com página pública em /p/{slug}.
 * Tudo o que a pessoa monta (perfil, tema, SEO e seções em ordem) fica em `dados` (JSON) e sempre passa por
 * normalizar(): o que vem do navegador nunca vai direto para a página.
 * Imagens: token de App\Support\Midia (envio em pedaços, GIF até 25 MB) do próprio dono, ou um endereço https.
 */
class Portfolio extends Model
{
    protected $table = 'portfolios';

    protected $fillable = ['slug', 'publicado', 'dados'];

    protected $casts = ['dados' => 'array', 'publicado' => 'boolean', 'visitas' => 'integer'];

    public const MAX_SECOES = 20;
    public const MAX_ITENS = 40;
    public const MAX_LINKS = 12;

    /** Endereços que não podem virar portfólio. */
    public const RESERVADOS = ['admin', 'api', 'p', 'portfolio', 'portfolios', 'work-flower', 'workflower', 'websight', 'planos', 'login', 'registro', 'contato', 'suporte', 'loja', 'midia'];

    /** Tipos de seção: nome no editor e quais campos cada item usa (o editor mostra só esses). */
    public const TIPOS = [
        'sobre'       => ['nome' => 'Sobre mim',     'icone' => '👋', 'itens' => []],
        'projetos'    => ['nome' => 'Projetos',      'icone' => '🗂️', 'itens' => ['titulo', 'subtitulo', 'texto', 'img', 'link', 'tags']],
        'experiencia' => ['nome' => 'Experiência',   'icone' => '💼', 'itens' => ['titulo', 'subtitulo', 'periodo', 'texto']],
        'formacao'    => ['nome' => 'Formação',      'icone' => '🎓', 'itens' => ['titulo', 'subtitulo', 'periodo', 'texto']],
        'habilidades' => ['nome' => 'Habilidades',   'icone' => '⚡', 'itens' => ['titulo', 'nivel']],
        'servicos'    => ['nome' => 'Serviços',      'icone' => '🛠️', 'itens' => ['titulo', 'texto', 'subtitulo']],
        'numeros'     => ['nome' => 'Números',       'icone' => '📈', 'itens' => ['titulo', 'subtitulo']],
        'depoimentos' => ['nome' => 'Depoimentos',   'icone' => '💬', 'itens' => ['texto', 'titulo', 'subtitulo', 'img']],
        'galeria'     => ['nome' => 'Galeria',       'icone' => '🖼️', 'itens' => ['img', 'titulo', 'link']],
        'links'       => ['nome' => 'Links',         'icone' => '🔗', 'itens' => ['titulo', 'link']],
        'texto'       => ['nome' => 'Texto livre',   'icone' => '📝', 'itens' => []],
        'contato'     => ['nome' => 'Contato',       'icone' => '✉️', 'itens' => []],
    ];

    /** Estilos de cada tipo de seção (o primeiro é o padrão). */
    public const ESTILOS = [
        'projetos'    => ['cartoes', 'lista', 'mosaico', 'destaque'],
        'experiencia' => ['linha', 'cartoes', 'simples'],
        'formacao'    => ['linha', 'cartoes', 'simples'],
        'habilidades' => ['barras', 'etiquetas', 'grade', 'circulos'],
        'servicos'    => ['cartoes', 'lista'],
        'numeros'     => ['grandes', 'cartoes'],
        'depoimentos' => ['cartoes', 'citacao'],
        'galeria'     => ['grade', 'mosaico', 'carrossel'],
        'links'       => ['botoes', 'lista'],
        'sobre'       => ['simples', 'cartao', 'dividido'],
        'texto'       => ['simples', 'cartao', 'destaque'],
        'contato'     => ['simples', 'cartao', 'dividido'],
    ];

    /** Letras: nome no editor, pilha CSS e família do Google Fonts (null = do sistema, não carrega nada). */
    public const FONTES = [
        'sistema'      => ['Do sistema', "system-ui, -apple-system, 'Segoe UI', Roboto, Arial, sans-serif", null],
        'inter'        => ['Inter', "'Inter', system-ui, sans-serif", 'Inter:wght@400;600;800'],
        'poppins'      => ['Poppins', "'Poppins', system-ui, sans-serif", 'Poppins:wght@400;600;800'],
        'montserrat'   => ['Montserrat', "'Montserrat', system-ui, sans-serif", 'Montserrat:wght@400;600;800'],
        'space'        => ['Space Grotesk', "'Space Grotesk', system-ui, sans-serif", 'Space+Grotesk:wght@400;600;700'],
        'nunito'       => ['Nunito', "'Nunito', system-ui, sans-serif", 'Nunito:wght@400;700;900'],
        'dmsans'       => ['DM Sans', "'DM Sans', system-ui, sans-serif", 'DM+Sans:wght@400;600;800'],
        'playfair'     => ['Playfair Display', "'Playfair Display', Georgia, serif", 'Playfair+Display:wght@400;700;900'],
        'dmserif'      => ['DM Serif Display', "'DM Serif Display', Georgia, serif", 'DM+Serif+Display'],
        'merriweather' => ['Merriweather', "'Merriweather', Georgia, serif", 'Merriweather:wght@400;700;900'],
        'lora'         => ['Lora', "'Lora', Georgia, serif", 'Lora:wght@400;600;700'],
        'georgia'      => ['Georgia', "Georgia, 'Times New Roman', serif", null],
        'bebas'        => ['Bebas Neue', "'Bebas Neue', Impact, sans-serif", 'Bebas+Neue'],
        'oswald'       => ['Oswald', "'Oswald', Impact, sans-serif", 'Oswald:wght@400;600;700'],
        'mono'         => ['JetBrains Mono', "'JetBrains Mono', ui-monospace, Consolas, monospace", 'JetBrains+Mono:wght@400;600;800'],
        'caveat'       => ['Caveat (à mão)', "'Caveat', 'Segoe Print', cursive", 'Caveat:wght@400;700'],
        'pacifico'     => ['Pacifico', "'Pacifico', cursive", 'Pacifico'],
    ];

    /** Opções do tema (o primeiro valor de cada lista é o padrão). */
    public const OPCOES = [
        'layout'        => ['classico', 'lateral', 'centralizado', 'revista', 'cartao'],
        'cabecalho'     => ['simples', 'capa', 'dividido', 'minimo', 'gigante'],
        'cantos'        => ['suaves', 'retos', 'redondos'],
        'sombra'        => ['leve', 'nenhuma', 'forte', 'contorno'],
        'fundo_tipo'    => ['cor', 'gradiente', 'imagem', 'padrao'],
        'padrao'        => ['pontos', 'grade', 'linhas', 'xadrez'],
        'largura'       => ['normal', 'estreita', 'larga'],
        'espacamento'   => ['normal', 'compacto', 'amplo'],
        'tamanho_texto' => ['m', 'p', 'g'],
        'titulos'       => ['normal', 'maiusculas', 'sublinhado', 'numerados'],
        'foto_forma'    => ['circulo', 'quadrado', 'arredondada', 'nenhuma'],
        'animacao'      => ['subir', 'nenhuma', 'aparecer', 'zoom'],
    ];

    /** Temas prontos: escolher um preenche as cores e as letras (dá para mudar tudo depois). */
    public const TEMAS = [
        'claro'     => ['nome' => 'Claro',          'cor' => '#2563eb', 'cor2' => '#7c3aed', 'fundo' => '#f8fafc', 'texto' => '#0f172a', 'cartao' => '#ffffff', 'fonte_titulo' => 'inter',      'fonte_texto' => 'inter',   'fundo_tipo' => 'cor'],
        'escuro'    => ['nome' => 'Escuro',         'cor' => '#60a5fa', 'cor2' => '#a78bfa', 'fundo' => '#0b1020', 'texto' => '#e5e7eb', 'cartao' => '#151b2e', 'fonte_titulo' => 'space',      'fonte_texto' => 'inter',   'fundo_tipo' => 'cor'],
        'minimal'   => ['nome' => 'Minimalista',    'cor' => '#111111', 'cor2' => '#555555', 'fundo' => '#ffffff', 'texto' => '#111111', 'cartao' => '#f4f4f4', 'fonte_titulo' => 'dmsans',     'fonte_texto' => 'dmsans',  'fundo_tipo' => 'cor'],
        'editorial' => ['nome' => 'Editorial',      'cor' => '#b91c1c', 'cor2' => '#1f2937', 'fundo' => '#faf7f2', 'texto' => '#1c1917', 'cartao' => '#ffffff', 'fonte_titulo' => 'playfair',   'fonte_texto' => 'lora',    'fundo_tipo' => 'cor'],
        'oceano'    => ['nome' => 'Oceano',         'cor' => '#0891b2', 'cor2' => '#2563eb', 'fundo' => '#ecfeff', 'texto' => '#083344', 'cartao' => '#ffffff', 'fonte_titulo' => 'poppins',    'fonte_texto' => 'nunito',  'fundo_tipo' => 'gradiente'],
        'floresta'  => ['nome' => 'Floresta',       'cor' => '#15803d', 'cor2' => '#a16207', 'fundo' => '#f0fdf4', 'texto' => '#14281d', 'cartao' => '#ffffff', 'fonte_titulo' => 'merriweather', 'fonte_texto' => 'nunito', 'fundo_tipo' => 'cor'],
        'por-do-sol' => ['nome' => 'Pôr do sol',    'cor' => '#ea580c', 'cor2' => '#db2777', 'fundo' => '#fff7ed', 'texto' => '#431407', 'cartao' => '#ffffff', 'fonte_titulo' => 'montserrat', 'fonte_texto' => 'dmsans',  'fundo_tipo' => 'gradiente'],
        'neon'      => ['nome' => 'Neon',           'cor' => '#22d3ee', 'cor2' => '#f472b6', 'fundo' => '#05010f', 'texto' => '#f5f3ff', 'cartao' => '#140b2b', 'fonte_titulo' => 'space',      'fonte_texto' => 'space',   'fundo_tipo' => 'padrao'],
        'terminal'  => ['nome' => 'Terminal',       'cor' => '#22c55e', 'cor2' => '#eab308', 'fundo' => '#0a0f0a', 'texto' => '#d1fae5', 'cartao' => '#111a11', 'fonte_titulo' => 'mono',       'fonte_texto' => 'mono',    'fundo_tipo' => 'padrao'],
        'lavanda'   => ['nome' => 'Lavanda',        'cor' => '#7c3aed', 'cor2' => '#db2777', 'fundo' => '#faf5ff', 'texto' => '#2e1065', 'cartao' => '#ffffff', 'fonte_titulo' => 'nunito',     'fonte_texto' => 'nunito',  'fundo_tipo' => 'gradiente'],
        'cafe'      => ['nome' => 'Café',           'cor' => '#92400e', 'cor2' => '#065f46', 'fundo' => '#f5ede3', 'texto' => '#2b1d12', 'cartao' => '#fffaf3', 'fonte_titulo' => 'dmserif',    'fonte_texto' => 'lora',    'fundo_tipo' => 'cor'],
        'grafite'   => ['nome' => 'Grafite',        'cor' => '#f59e0b', 'cor2' => '#f97316', 'fundo' => '#18181b', 'texto' => '#fafafa', 'cartao' => '#27272a', 'fonte_titulo' => 'oswald',     'fonte_texto' => 'inter',   'fundo_tipo' => 'cor'],
        'retro'     => ['nome' => 'Retrô',          'cor' => '#e11d48', 'cor2' => '#0d9488', 'fundo' => '#fef3c7', 'texto' => '#1f1300', 'cartao' => '#fffbeb', 'fonte_titulo' => 'bebas',      'fonte_texto' => 'space',   'fundo_tipo' => 'padrao'],
        'artista'   => ['nome' => 'Artista',        'cor' => '#c026d3', 'cor2' => '#f59e0b', 'fundo' => '#fdf4ff', 'texto' => '#3b0764', 'cartao' => '#ffffff', 'fonte_titulo' => 'caveat',     'fonte_texto' => 'nunito',  'fundo_tipo' => 'gradiente'],
        'corporativo' => ['nome' => 'Corporativo',  'cor' => '#1d4ed8', 'cor2' => '#0f766e', 'fundo' => '#f1f5f9', 'texto' => '#0f172a', 'cartao' => '#ffffff', 'fonte_titulo' => 'montserrat', 'fonte_texto' => 'inter',   'fundo_tipo' => 'cor'],
        'meia-noite' => ['nome' => 'Meia-noite',    'cor' => '#818cf8', 'cor2' => '#f472b6', 'fundo' => '#020617', 'texto' => '#e2e8f0', 'cartao' => '#0f172a', 'fonte_titulo' => 'playfair',   'fonte_texto' => 'inter',   'fundo_tipo' => 'gradiente'],
    ];

    public const REDES = [
        'linkedin' => 'LinkedIn', 'github' => 'GitHub', 'instagram' => 'Instagram', 'behance' => 'Behance', 'dribbble' => 'Dribbble',
        'youtube' => 'YouTube', 'tiktok' => 'TikTok', 'x' => 'X (Twitter)', 'whatsapp' => 'WhatsApp', 'email' => 'E-mail', 'site' => 'Site', 'outro' => 'Outro',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function mensagens(): HasMany
    {
        return $this->hasMany(PortfolioMensagem::class)->latest('id');
    }

    /** Dados prontos para a página (o que estiver salvo, sempre normalizado). */
    public function conteudo(): array
    {
        return self::normalizar(is_array($this->dados) ? $this->dados : [], (int) $this->user_id);
    }

    /** Plano gratuito mostra "Feito com Work Flower" no rodapé. */
    public function comMarca(): bool
    {
        return Planos::slugDe($this->user) === Planos::PADRAO;
    }

    /** O formulário de contato aparece (e aceita mensagens): seção Contato visível com "formulário" ligado. */
    public function aceitaMensagens(?array $c = null): bool
    {
        $c ??= $this->conteudo();
        foreach ($c['secoes'] as $s) {
            if ($s['tipo'] === 'contato' && $s['visivel'] && $s['formulario']) return true;
        }

        return false;
    }

    /** O token de mídia aparece em algum lugar deste portfólio (só esses saem pela rota pública). */
    public function usaMidia(string $token): bool
    {
        return Midia::tokenValido($token) && str_contains(json_encode($this->dados ?? []), $token);
    }

    /* ================= conteúdo inicial e normalização ================= */

    public static function inicial(User $u): array
    {
        $sec = fn (string $tipo, array $extra = []) => array_merge(['id' => self::novoId(), 'tipo' => $tipo, 'titulo' => self::TIPOS[$tipo]['nome']], $extra);

        return self::normalizar([
            'perfil' => ['nome' => $u->name, 'titulo' => '', 'resumo' => '', 'email' => '', 'disponivel' => true, 'botao_texto' => 'Me contrate'],
            'tema'   => ['preset' => 'claro'] + self::TEMAS['claro'],
            'secoes' => [
                $sec('sobre'),
                $sec('projetos'),
                $sec('experiencia'),
                $sec('habilidades', ['estilo' => 'etiquetas']),
                $sec('depoimentos'),
                $sec('contato', ['titulo' => 'Vamos conversar?', 'texto' => 'Conte rapidinho o que você precisa que eu respondo.', 'formulario' => true]),
            ],
        ], $u->id);
    }

    public static function novoId(): string
    {
        return substr(bin2hex(random_bytes(6)), 0, 10);
    }

    /** Tudo o que vem do editor passa por aqui: só campos conhecidos, tamanhos limitados, cores e opções válidas. */
    public static function normalizar(array $d, int $uid): array
    {
        $p = is_array($d['perfil'] ?? null) ? $d['perfil'] : [];
        $t = is_array($d['tema'] ?? null) ? $d['tema'] : [];
        $s = is_array($d['seo'] ?? null) ? $d['seo'] : [];
        $base = self::TEMAS[$t['preset'] ?? ''] ?? self::TEMAS['claro'];

        $links = [];
        foreach (array_slice(is_array($p['links'] ?? null) ? $p['links'] : [], 0, self::MAX_LINKS) as $l) {
            if (! is_array($l)) continue;
            $rede = isset(self::REDES[$l['rede'] ?? '']) ? $l['rede'] : 'outro';
            $url = self::link($l['url'] ?? null, $rede);
            if ($url) $links[] = ['rede' => $rede, 'url' => $url, 'rotulo' => self::txt($l['rotulo'] ?? '', 40)];
        }

        $tema = ['preset' => isset(self::TEMAS[$t['preset'] ?? '']) ? $t['preset'] : 'claro'];
        foreach (['cor', 'cor2', 'fundo', 'texto', 'cartao'] as $k) $tema[$k] = self::cor($t[$k] ?? null, $base[$k]);
        foreach (['fonte_titulo', 'fonte_texto'] as $k) $tema[$k] = isset(self::FONTES[$t[$k] ?? '']) ? $t[$k] : $base[$k];
        foreach (self::OPCOES as $k => $ops) $tema[$k] = in_array($t[$k] ?? null, $ops, true) ? $t[$k] : ($k === 'fundo_tipo' ? $base['fundo_tipo'] : $ops[0]);
        $tema['fundo_img'] = self::img($t['fundo_img'] ?? null, $uid);
        $tema['fundo_escurecer'] = self::num($t['fundo_escurecer'] ?? 30, 0, 90);
        $tema['fundo_desfoque'] = self::num($t['fundo_desfoque'] ?? 0, 0, 20);
        $tema['fundo_fixo'] = self::bool($t['fundo_fixo'] ?? true);
        $tema['vidro'] = self::bool($t['vidro'] ?? false);
        $tema['menu'] = self::bool($t['menu'] ?? true);
        $tema['modo_escuro'] = self::bool($t['modo_escuro'] ?? false); // botão para o visitante trocar claro/escuro

        $secoes = [];
        $ids = [];
        foreach (array_slice(is_array($d['secoes'] ?? null) ? $d['secoes'] : [], 0, self::MAX_SECOES) as $x) {
            if (! is_array($x) || ! isset(self::TIPOS[$x['tipo'] ?? ''])) continue;
            $tipo = $x['tipo'];
            $id = is_string($x['id'] ?? null) && preg_match('/^[a-z0-9]{1,16}$/', $x['id']) && ! isset($ids[$x['id']]) ? $x['id'] : self::novoId();
            $ids[$id] = true;
            $itens = [];
            foreach (array_slice(is_array($x['itens'] ?? null) ? $x['itens'] : [], 0, self::MAX_ITENS) as $i) {
                if (is_array($i) && ($n = self::item($i, $tipo, $uid))) $itens[] = $n;
            }
            $secoes[] = [
                'id'         => $id,
                'tipo'       => $tipo,
                'titulo'     => self::txt($x['titulo'] ?? self::TIPOS[$tipo]['nome'], 80),
                'subtitulo'  => self::txt($x['subtitulo'] ?? '', 200),
                'texto'      => self::txt($x['texto'] ?? '', 5000, true),
                'visivel'    => self::bool($x['visivel'] ?? true),
                'estilo'     => in_array($x['estilo'] ?? null, self::ESTILOS[$tipo], true) ? $x['estilo'] : self::ESTILOS[$tipo][0],
                'colunas'    => self::num($x['colunas'] ?? 3, 1, 4),
                'fundo'      => in_array($x['fundo'] ?? null, ['nenhum', 'cartao', 'destaque', 'invertido'], true) ? $x['fundo'] : 'nenhum',
                'formulario' => self::bool($x['formulario'] ?? false),
                'img'        => self::img($x['img'] ?? null, $uid),
                'itens'      => $itens,
            ];
        }

        return [
            'perfil' => [
                'nome'        => self::txt($p['nome'] ?? '', 80),
                'titulo'      => self::txt($p['titulo'] ?? '', 120),
                'resumo'      => self::txt($p['resumo'] ?? '', 600, true),
                'foto'        => self::img($p['foto'] ?? null, $uid),
                'capa'        => self::img($p['capa'] ?? null, $uid),
                'local'       => self::txt($p['local'] ?? '', 80),
                'email'       => filter_var(trim((string) ($p['email'] ?? '')), FILTER_VALIDATE_EMAIL) ? mb_substr(trim($p['email']), 0, 120) : '',
                'telefone'    => self::txt(preg_replace('/[^0-9 ()+\-]/', '', (string) ($p['telefone'] ?? '')), 30),
                'disponivel'  => self::bool($p['disponivel'] ?? false),
                'disponivel_texto' => self::txt($p['disponivel_texto'] ?? '', 60),
                'botao_texto' => self::txt($p['botao_texto'] ?? '', 30),
                'botao_link'  => self::link($p['botao_link'] ?? null) ?? '',
                'cv'          => self::link($p['cv'] ?? null) ?? '',
                'links'       => $links,
            ],
            'tema'   => $tema,
            'seo'    => [
                'titulo'    => self::txt($s['titulo'] ?? '', 70),
                'descricao' => self::txt($s['descricao'] ?? '', 170),
                'imagem'    => self::img($s['imagem'] ?? null, $uid),
                'indexar'   => self::bool($s['indexar'] ?? true),
            ],
            'secoes' => $secoes,
        ];
    }

    private static function item(array $i, string $tipo, int $uid): ?array
    {
        $campos = self::TIPOS[$tipo]['itens'];
        if (! $campos) return null;
        $n = ['id' => is_string($i['id'] ?? null) && preg_match('/^[a-z0-9]{1,16}$/', $i['id']) ? $i['id'] : self::novoId()];
        foreach ($campos as $c) {
            $n[$c] = match ($c) {
                'titulo'    => self::txt($i['titulo'] ?? '', 120),
                'subtitulo' => self::txt($i['subtitulo'] ?? '', 120),
                'periodo'   => self::txt($i['periodo'] ?? '', 60),
                'texto'     => self::txt($i['texto'] ?? '', 2000, true),
                'img'       => self::img($i['img'] ?? null, $uid),
                'link'      => self::link($i['link'] ?? null) ?? '',
                'tags'      => self::txt($i['tags'] ?? '', 200),
                'nivel'     => self::num($i['nivel'] ?? 80, 0, 100),
            };
        }
        // item sem nada escrito (nem imagem) não vale
        $tem = false;
        foreach ($n as $k => $v) if ($k !== 'id' && $k !== 'nivel' && $v !== '' && $v !== null) $tem = true;

        return $tem ? $n : null;
    }

    private static function txt(mixed $v, int $max, bool $linhas = false): string
    {
        $v = is_scalar($v) ? (string) $v : '';
        $v = str_replace("\r\n", "\n", $v);
        $v = preg_replace($linhas ? '/[^\P{C}\n]/u' : '/\p{C}/u', '', $v) ?? '';
        if ($linhas) $v = preg_replace("/\n{3,}/", "\n\n", $v);

        return mb_substr(trim($v), 0, $max);
    }

    private static function bool(mixed $v): bool
    {
        return filter_var($v, FILTER_VALIDATE_BOOLEAN);
    }

    private static function num(mixed $v, int $min, int $max): int
    {
        return max($min, min($max, (int) $v));
    }

    private static function cor(mixed $c, string $padrao): string
    {
        return is_string($c) && preg_match('/^#[0-9a-f]{6}$/i', $c) ? strtolower($c) : $padrao;
    }

    /** Imagem: token de mídia do próprio dono ou endereço https. Qualquer outra coisa vira null. */
    public static function img(mixed $v, int $uid): ?string
    {
        if (! is_string($v) || $v === '') return null;
        $v = trim($v);
        if (Midia::tokenValido($v)) return Midia::doUsuario($uid, $v) ? $v : null;

        return self::link($v) && str_starts_with($v, 'https://') ? mb_substr($v, 0, 500) : null;
    }

    /** Link: http(s), mailto, tel, wa.me ou âncora da própria página (#contato). @usuario vira o link da rede. */
    public static function link(mixed $v, ?string $rede = null): ?string
    {
        if (! is_string($v)) return null;
        $v = trim($v);
        if ($v === '' || mb_strlen($v) > 500) return null;
        if (preg_match('/^#[a-z0-9-]{1,40}$/', $v)) return $v;
        if ($rede === 'email' && filter_var($v, FILTER_VALIDATE_EMAIL)) return 'mailto:' . $v;
        if ($rede === 'whatsapp' && preg_match('/^[0-9 ()+\-]{8,20}$/', $v)) {
            $n = preg_replace('/\D/', '', $v);
            return 'https://wa.me/' . (strlen($n) <= 11 ? '55' . $n : $n);
        }
        if ($rede && str_starts_with($v, '@') && preg_match('/^@[A-Za-z0-9._-]{1,40}$/', $v)) {
            $u = substr($v, 1);
            $base = ['instagram' => 'https://instagram.com/', 'github' => 'https://github.com/', 'tiktok' => 'https://tiktok.com/@', 'x' => 'https://x.com/', 'youtube' => 'https://youtube.com/@', 'behance' => 'https://behance.net/', 'dribbble' => 'https://dribbble.com/'][$rede] ?? null;
            if ($base) return $base . $u;
        }
        if (preg_match('/^(mailto:[^\s<>"]+|tel:[0-9+() -]{6,25})$/i', $v)) return $v;
        if (! preg_match('#^https?://#i', $v) && preg_match('/^[a-z0-9-]+(\.[a-z0-9-]+)+(\/\S*)?$/i', $v)) $v = 'https://' . $v;
        if (! preg_match('#^https?://[^\s<>"\']+$#i', $v) || ! filter_var($v, FILTER_VALIDATE_URL)) return null;

        return $v;
    }
}
