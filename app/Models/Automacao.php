<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Automação do computador: um gatilho (horário, intervalo, tarefa criada/concluída ou botão)
 * dispara uma lista de ações que o agente instalado no Windows executa.
 * O site só guarda o pedido; quem decide o que pode rodar é o agente (lista de ações permitidas).
 */
class Automacao extends Model
{
    protected $table = 'automacoes';

    protected $fillable = ['user_id', 'dispositivo_id', 'nome', 'ativa', 'gatilho', 'gatilho_config', 'acoes', 'proxima_em', 'ultima_em'];

    protected $casts = [
        'ativa'          => 'boolean',
        'gatilho_config' => 'array',
        'acoes'          => 'array',
        'proxima_em'     => 'datetime',
        'ultima_em'      => 'datetime',
    ];

    /** Fuso dos horários que a pessoa digita (o servidor trabalha em UTC). */
    public const FUSO = 'America/Sao_Paulo';

    public const GATILHOS = [
        'horario'          => 'Em um horário',
        'intervalo'        => 'A cada X minutos',
        'tarefa_criada'    => 'Quando uma tarefa é criada',
        'tarefa_concluida' => 'Quando uma tarefa é concluída',
        'manual'           => 'Só pelo botão',
    ];

    public const ACOES = [
        'notificacao' => 'Mostrar notificação',
        'url'         => 'Abrir site (URL)',
        'abrir'       => 'Abrir programa ou arquivo',
        'script'      => 'Rodar script salvo no PC',
        'backup'      => 'Copiar pasta (backup)',
        'digitar'     => 'Digitar texto',
    ];

    public const DIAS = [1 => 'Seg', 2 => 'Ter', 3 => 'Qua', 4 => 'Qui', 5 => 'Sex', 6 => 'Sáb', 0 => 'Dom'];

    public const MAX_ACOES = 10;

    /** Programas que interpretam comandos: o site recusa (o agente também), para "abrir" não virar terminal remoto. */
    public const EXTENSOES_PROIBIDAS = ['bat', 'cmd', 'com', 'ps1', 'psm1', 'vbs', 'vbe', 'js', 'jse', 'wsf', 'wsh', 'hta', 'msi', 'scr', 'reg', 'pif', 'cpl', 'lnk', 'url'];

    public const PROGRAMAS_PROIBIDOS = ['cmd.exe', 'powershell.exe', 'pwsh.exe', 'wscript.exe', 'cscript.exe', 'mshta.exe', 'rundll32.exe', 'regsvr32.exe', 'reg.exe', 'bash.exe', 'wsl.exe', 'msiexec.exe', 'certutil.exe', 'bitsadmin.exe', 'schtasks.exe'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function dispositivo(): BelongsTo
    {
        return $this->belongsTo(AutomacaoDispositivo::class, 'dispositivo_id');
    }

    public function execucoes(): HasMany
    {
        return $this->hasMany(AutomacaoExecucao::class);
    }

    // ---------------------------------------------------------------- validação

    /** Confere e limpa as ações vindas do formulário. Lança erro de validação em português. */
    public static function limparAcoes(mixed $acoes): array
    {
        if (! is_array($acoes) || ! $acoes) {
            throw ValidationException::withMessages(['acoes' => 'Adicione pelo menos uma ação.']);
        }
        if (count($acoes) > self::MAX_ACOES) {
            throw ValidationException::withMessages(['acoes' => 'No máximo ' . self::MAX_ACOES . ' ações por automação.']);
        }

        $txt = fn ($v, int $max) => mb_substr(trim(is_scalar($v) ? (string) $v : ''), 0, $max);
        $erro = fn (string $m) => throw ValidationException::withMessages(['acoes' => $m]);
        $limpas = [];

        foreach (array_values($acoes) as $i => $a) {
            $n = $i + 1;
            $tipo = is_array($a) ? ($a['tipo'] ?? null) : null;
            if (! is_string($tipo) || ! isset(self::ACOES[$tipo])) {
                $erro("Ação $n: escolha o tipo.");
            }

            switch ($tipo) {
                case 'notificacao':
                    $l = ['titulo' => $txt($a['titulo'] ?? '', 80), 'mensagem' => $txt($a['mensagem'] ?? '', 300)];
                    if ($l['titulo'] === '' && $l['mensagem'] === '') {
                        $erro("Ação $n: escreva o título ou a mensagem da notificação.");
                    }
                    break;

                case 'url':
                    $u = $txt($a['url'] ?? '', 500);
                    if (! preg_match('#^https?://[^\s]+$#i', $u) || ! filter_var($u, FILTER_VALIDATE_URL)) {
                        $erro("Ação $n: o endereço precisa começar com http:// ou https://.");
                    }
                    $l = ['url' => $u];
                    break;

                case 'abrir':
                    $c = trim($txt($a['caminho'] ?? '', 400), " \"'");
                    if ($c === '') {
                        $erro("Ação $n: informe o caminho do programa ou arquivo.");
                    }
                    if (self::caminhoProibido($c)) {
                        $erro("Ação $n: por segurança, \"abrir\" não aceita terminais nem scripts (.bat, .ps1, cmd, PowerShell...). Use a ação \"Rodar script salvo no PC\".");
                    }
                    $l = ['caminho' => $c];
                    break;

                case 'script':
                    $s = $txt($a['nome'] ?? '', 60);
                    $s = preg_replace('/\.ps1$/i', '', $s);
                    if (! preg_match('/^[A-Za-z0-9][A-Za-z0-9 _.-]{0,59}$/', $s) || str_contains($s, '..')) {
                        $erro("Ação $n: o nome do script tem só letras, números, espaço, ponto, - e _ (sem pastas).");
                    }
                    $l = ['nome' => $s];
                    break;

                case 'backup':
                    $l = ['origem' => trim($txt($a['origem'] ?? '', 400), " \"'"), 'destino' => trim($txt($a['destino'] ?? '', 400), " \"'")];
                    if ($l['origem'] === '' || $l['destino'] === '') {
                        $erro("Ação $n: informe a pasta de origem e a pasta onde guardar a cópia.");
                    }
                    break;

                case 'digitar':
                    $t = mb_substr(is_scalar($a['texto'] ?? null) ? (string) $a['texto'] : '', 0, 500);
                    if (trim($t) === '') {
                        $erro("Ação $n: escreva o texto a digitar.");
                    }
                    $l = ['texto' => $t, 'espera' => max(0, min(30, (int) ($a['espera'] ?? 3)))];
                    break;
            }

            $limpas[] = ['tipo' => $tipo] + $l;
        }

        return $limpas;
    }

    public static function caminhoProibido(string $caminho): bool
    {
        $base = strtolower(basename(str_replace('\\', '/', trim($caminho))));
        $ext = pathinfo($base, PATHINFO_EXTENSION);

        return in_array($base, self::PROGRAMAS_PROIBIDOS, true)
            || in_array($base . '.exe', self::PROGRAMAS_PROIBIDOS, true)
            || in_array($ext, self::EXTENSOES_PROIBIDAS, true);
    }

    /** Confere e limpa a configuração do gatilho. */
    public static function limparGatilho(string $gatilho, array $cfg): array
    {
        return match ($gatilho) {
            'horario' => (function () use ($cfg) {
                $hora = (string) ($cfg['hora'] ?? '');
                if (! preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $hora)) {
                    throw ValidationException::withMessages(['hora' => 'Informe a hora no formato 08:30.']);
                }
                $dias = array_values(array_unique(array_map('intval', array_filter((array) ($cfg['dias'] ?? []), 'is_numeric'))));
                $dias = array_values(array_filter($dias, fn ($d) => $d >= 0 && $d <= 6));
                sort($dias);

                return ['hora' => $hora, 'dias' => $dias]; // dias vazio = todos os dias
            })(),
            'intervalo' => (function () use ($cfg) {
                $m = (int) ($cfg['minutos'] ?? 0);
                if ($m < 5 || $m > 1440) {
                    throw ValidationException::withMessages(['minutos' => 'O intervalo vai de 5 a 1440 minutos.']);
                }

                return ['minutos' => $m];
            })(),
            'tarefa_criada', 'tarefa_concluida' => ['area' => isset(Tarefa::AREAS[$cfg['area'] ?? '']) ? $cfg['area'] : ''],
            default => [],
        };
    }

    // ---------------------------------------------------------------- horários

    /** Próximo disparo depois de $depois (em UTC), ou null para gatilhos sem horário. */
    public function proximaApos(CarbonInterface $depois): ?Carbon
    {
        $cfg = $this->gatilho_config ?? [];

        if ($this->gatilho === 'intervalo') {
            return Carbon::instance($depois)->addMinutes(max(5, (int) ($cfg['minutos'] ?? 60)))->setSecond(0)->utc();
        }
        if ($this->gatilho !== 'horario' || empty($cfg['hora'])) {
            return null;
        }

        [$h, $m] = array_map('intval', explode(':', $cfg['hora']));
        $dias = $cfg['dias'] ?? [];
        $base = Carbon::instance($depois)->setTimezone(self::FUSO);
        for ($i = 0; $i <= 7; $i++) {
            $c = $base->copy()->startOfDay()->addDays($i)->setTime($h, $m);
            if ($c->gt($base) && (! $dias || in_array($c->dayOfWeek, $dias, true))) {
                return $c->utc();
            }
        }

        return null;
    }

    /** Texto curto do gatilho para a lista ("Seg, Qua às 08:30"). */
    public function descricaoGatilho(): string
    {
        $cfg = $this->gatilho_config ?? [];

        return match ($this->gatilho) {
            'horario' => (empty($cfg['dias']) ? 'Todo dia' : implode(', ', array_map(fn ($d) => self::DIAS[$d] ?? $d, $cfg['dias']))) . ' às ' . ($cfg['hora'] ?? '?'),
            'intervalo' => 'A cada ' . ($cfg['minutos'] ?? '?') . ' min',
            'tarefa_criada', 'tarefa_concluida' => self::GATILHOS[$this->gatilho] . (! empty($cfg['area']) ? ' em ' . (Tarefa::AREAS[$cfg['area']]['nome'] ?? $cfg['area']) : ''),
            default => self::GATILHOS[$this->gatilho] ?? $this->gatilho,
        };
    }

    // ---------------------------------------------------------------- disparos

    /**
     * Cria a execução pendente para o agente buscar. Troca {tarefa}, {area}, {data} e {hora} nos textos.
     * Sem computador pareado (ou revogado) não há o que disparar.
     */
    public function disparar(string $origem, array $vars = []): ?AutomacaoExecucao
    {
        $disp = $this->dispositivo;
        if (! $disp || ! $disp->ativo()) {
            return null;
        }

        $agora = now()->setTimezone(self::FUSO);
        $troca = ['{tarefa}' => $vars['tarefa'] ?? '', '{area}' => $vars['area'] ?? '', '{data}' => $agora->format('d/m/Y'), '{hora}' => $agora->format('H:i')];
        $acoes = array_map(function (array $a) use ($troca) {
            foreach (['titulo', 'mensagem', 'texto'] as $campo) {
                if (isset($a[$campo])) {
                    $a[$campo] = strtr($a[$campo], $troca);
                }
            }
            if (isset($a['url'])) {
                $a['url'] = strtr($a['url'], array_map('rawurlencode', $troca));
            }

            return $a;
        }, $this->acoes ?? []);

        $this->forceFill(['ultima_em' => now()])->save();

        return AutomacaoExecucao::create([
            'user_id'        => $this->user_id,
            'automacao_id'   => $this->id,
            'dispositivo_id' => $disp->id,
            'nome'           => $this->nome,
            'origem'         => mb_substr($origem, 0, 160),
            'acoes'          => $acoes,
            'status'         => 'pendente',
        ]);
    }

    /** Dispara as automações de horário/intervalo vencidas deste aparelho e agenda a próxima vez. */
    public static function processarHorarios(AutomacaoDispositivo $disp): void
    {
        $vencidas = self::where('dispositivo_id', $disp->id)
            ->where('ativa', true)
            ->whereIn('gatilho', ['horario', 'intervalo'])
            ->whereNotNull('proxima_em')
            ->where('proxima_em', '<=', now())
            ->get();

        foreach ($vencidas as $a) {
            $quando = $a->proxima_em->copy()->setTimezone(self::FUSO)->format('d/m H:i');
            // só uma vez mesmo que o PC tenha ficado desligado vários horários
            $a->proxima_em = $a->proximaApos(now());
            $a->save();
            $a->disparar(($a->gatilho === 'horario' ? 'Horário ' : 'Intervalo, ') . $quando);
        }
    }

    /** Tarefa criada ou concluída: dispara as automações do dono que esperam esse evento. */
    public static function aoMudarTarefa(Tarefa $t, string $evento): void
    {
        $gatilho = $evento === 'criada' ? 'tarefa_criada' : 'tarefa_concluida';
        $lista = self::where('user_id', $t->user_id)->where('ativa', true)->where('gatilho', $gatilho)->get();

        foreach ($lista as $a) {
            $area = $a->gatilho_config['area'] ?? '';
            if ($area !== '' && $area !== $t->area) {
                continue;
            }
            $a->disparar(
                ($evento === 'criada' ? 'Tarefa criada: ' : 'Tarefa concluída: ') . mb_substr($t->titulo, 0, 100),
                ['tarefa' => $t->titulo, 'area' => Tarefa::AREAS[$t->area]['nome'] ?? $t->area],
            );
        }
    }
}
