<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Response;

/**
 * Work Flower como aplicativo instalável (PWA): manifesto, service worker e página "sem conexão".
 *
 * Os três saem pelo Laravel (e não como arquivos em public/) para que os endereços usem a base real
 * do site: em produção ele fica numa subpasta (https://websight.com.br/work-flower), e o start_url,
 * o scope e o escopo do service worker precisam começar nela. O sw.js em /work-flower/sw.js controla
 * só /work-flower/ — que é exatamente o app.
 */
class AppInstalavelController extends Controller
{
    /** Cor do fundo da tela de abertura do app (o mesmo "papel" escuro padrão do layout). */
    public const COR_FUNDO = '#0c1118';

    public function manifesto(): Response
    {
        $base = $this->base();
        $icone = fn (string $arq, int $lado, string $uso) => [
            'src'     => asset('icons/' . $arq) . '?v=' . @filemtime(public_path('icons/' . $arq)),
            'sizes'   => "{$lado}x{$lado}",
            'type'    => 'image/png',
            'purpose' => $uso,
        ];

        $manifesto = [
            'id'               => $base,
            'name'             => 'Work Flower',
            'short_name'       => 'Work Flower',
            'description'      => 'Organize corpo, mente, carteira, negócios e o resto em quadros de cartões.',
            'lang'             => 'pt-BR',
            'dir'              => 'ltr',
            'start_url'        => route('painel'),
            'scope'            => $base,
            'display'          => 'standalone',
            'display_override' => ['window-controls-overlay', 'standalone'],
            'orientation'      => 'any',
            'background_color' => self::COR_FUNDO,
            'theme_color'      => User::COR_CABECALHO_PADRAO,
            'categories'       => ['productivity', 'lifestyle', 'finance'],
            'icons'            => [
                $icone('icone-192.png', 192, 'any'),
                $icone('icone-512.png', 512, 'any'),
                $icone('icone-maskable-192.png', 192, 'maskable'),
                $icone('icone-maskable-512.png', 512, 'maskable'),
            ],
            'shortcuts' => collect(\App\Models\Tarefa::AREAS)->map(fn ($a, $slug) => [
                'name'  => $a['nome'],
                'url'   => route('areas.show', $slug),
                'icons' => [['src' => asset('icons/icone-192.png'), 'sizes' => '192x192', 'type' => 'image/png']],
            ])->values()->all(),
        ];

        return response(json_encode($manifesto, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), 200, [
            'Content-Type'  => 'application/manifest+json; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    public function serviceWorker(): Response
    {
        $base = $this->base();
        $offline = route('app.offline');

        // muda quando o próprio service worker, a página offline ou os ícones mudam: o navegador então
        // instala a versão nova e apaga o cache antigo
        $versao = substr(md5(implode('|', [
            @filemtime(resource_path('views/app/sw.blade.php')),
            @filemtime(resource_path('views/app/offline.blade.php')),
            @filemtime(public_path('icons/icone-192.png')),
            $base,
        ])), 0, 10);

        $js = view('app.sw', [
            'versao'   => $versao,
            'base'     => $base,
            'offline'  => $offline,
            'precache' => [
                $offline,
                asset('icons/icone-192.png'),
                asset('icons/icone-512.png'),
            ],
        ])->render();

        return response($js, 200, [
            'Content-Type'  => 'application/javascript; charset=utf-8',
            // o navegador sempre confere se o sw.js mudou; sem isso uma correção podia levar até 24 h
            'Cache-Control' => 'no-cache, max-age=0',
            'Service-Worker-Allowed' => parse_url($base, PHP_URL_PATH) ?: '/',
        ]);
    }

    public function offline()
    {
        return response()->view('app.offline', [], 200, ['Cache-Control' => 'public, max-age=3600']);
    }

    /**
     * Instalador leve para Windows: um .cmd de poucos KB, sem administrador e sem baixar programa nenhum.
     *
     * Ele roda um PowerShell (já vem no Windows 10/11, passado como -EncodedCommand para caber numa linha) que:
     * baixa o ícone da flor para %LOCALAPPDATA%\WorkFlower, cria os atalhos "Work Flower" na Área de Trabalho e no
     * Menu Iniciar abrindo o painel em janela própria (modo app do Chrome ou do Edge, o navegador padrão primeiro,
     * para usar o mesmo login já salvo nele), deixa um "Desinstalar Work Flower.cmd" na pasta e já abre o app.
     */
    public function instaladorWindows(): Response
    {
        $ps = $this->scriptWindows(route('painel'), asset('icons/work-flower.ico'));
        $codificado = base64_encode(mb_convert_encoding($ps, 'UTF-16LE', 'UTF-8'));

        $cmd = implode("\r\n", [
            '@echo off',
            'title Instalar o Work Flower',
            'echo.',
            'echo   Instalando o Work Flower neste computador (leva poucos segundos)...',
            'echo   Nada e instalado no sistema: so atalhos para abrir o app em janela propria.',
            'echo.',
            'powershell -NoProfile -ExecutionPolicy Bypass -EncodedCommand ' . $codificado,
            'if errorlevel 1 (echo. & echo   Nao deu certo. Tente de novo ou instale pelo navegador: botao "Baixar o app". & pause)',
            '',
        ]);

        return response($cmd, 200, [
            'Content-Type'        => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="Instalar Work Flower.cmd"',
            'Cache-Control'       => 'no-cache, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Instalador completo para Windows (só para quem está logado): o app (os mesmos atalhos do instalador leve)
     * + o agente do Work Flower com os textos rápidos funcionando em qualquer programa do Windows.
     *
     * Leva um código de uso único (30 min), não o token: o instalador troca o código pelo token do computador
     * (POST /api/agente-codigo), baixa o pacote do agente (/api/agente/pacote) e roda o instalar.ps1 dele com
     * -Expansor. O PowerShell é grande demais para uma linha do cmd.exe (8191 caracteres), então o .cmd escreve
     * o script em base64, em pedaços, num arquivo temporário, decodifica e roda.
     */
    public function instaladorWindowsCompleto(\Illuminate\Http\Request $request): Response
    {
        $codigo = \App\Http\Controllers\Api\AgenteCodigoController::gerar($request->user()->id);
        $ps = $this->scriptWindows(route('painel'), asset('icons/work-flower.ico'), $this->scriptAgente(rtrim(url('/'), '/'), $codigo));
        $pedacos = str_split(base64_encode("\u{FEFF}" . $ps), 900);

        $linhas = [
            '@echo off',
            'title Instalar o Work Flower (app + textos rapidos)',
            'echo.',
            'echo   Instalando o Work Flower e os textos rapidos neste computador...',
            'echo   Sem administrador. Leva menos de um minuto.',
            'echo.',
            'set "WF=%TEMP%\\wf-instalar-%RANDOM%%RANDOM%"',
            'type nul > "%WF%.b64"',
        ];
        // redirecionamento antes do echo: um pedaço que termine em dígito viraria "2>>" (saída de erro)
        foreach ($pedacos as $p) {
            $linhas[] = '>>"%WF%.b64" echo ' . $p;
        }
        $linhas[] = 'powershell -NoProfile -ExecutionPolicy Bypass -Command "$b = (Get-Content -LiteralPath \'%WF%.b64\' -Raw) -replace \'\\s\',\'\'; [IO.File]::WriteAllBytes(\'%WF%.ps1\', [Convert]::FromBase64String($b))"';
        $linhas[] = 'powershell -NoProfile -ExecutionPolicy Bypass -File "%WF%.ps1"';
        $linhas[] = 'set "ERRO=%ERRORLEVEL%"';
        $linhas[] = 'del "%WF%.b64" "%WF%.ps1" 2>nul';
        $linhas[] = 'if not "%ERRO%"=="0" (echo. & echo   Nao deu certo. Tente de novo ou baixe o instalador outra vez no site. & pause)';
        $linhas[] = '';

        return response(implode("\r\n", $linhas), 200, [
            'Content-Type'        => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="Instalar Work Flower.cmd"',
            'Cache-Control'       => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** Parte do instalador completo que instala o agente com os textos rápidos (público para os testes). */
    public function scriptAgente(string $site, string $codigo): string
    {
        $site = str_replace("'", "''", $site);
        $codigo = preg_replace('/[^A-Za-z0-9_]/', '', $codigo);

        return <<<PS
Write-Host ''
Write-Host '  Ligando os textos rapidos no Windows inteiro (agente do Work Flower)...'
try {
  \$site = '{$site}'
  \$corpo = @{ codigo = '{$codigo}'; computador = \$env:COMPUTERNAME } | ConvertTo-Json
  \$par = Invoke-RestMethod -Method Post -Uri "\$site/api/agente-codigo" -ContentType 'application/json' -Headers @{ Accept = 'application/json' } -Body \$corpo
  \$tmp = Join-Path \$env:TEMP ('wf-agente-' + [guid]::NewGuid().ToString('N'))
  New-Item -ItemType Directory -Force -Path \$tmp | Out-Null
  \$zip = Join-Path \$tmp 'agente.zip'
  Invoke-WebRequest -UseBasicParsing -Uri "\$site/api/agente/pacote" -Headers @{ Authorization = "Bearer \$(\$par.token)" } -OutFile \$zip
  Expand-Archive -LiteralPath \$zip -DestinationPath \$tmp -Force
  & powershell.exe -NoProfile -ExecutionPolicy Bypass -File (Join-Path \$tmp 'work-flower-agente\\instalar.ps1') -Site \$site -Token \$par.token -Expansor
  Remove-Item -LiteralPath \$tmp -Recurse -Force -ErrorAction SilentlyContinue
} catch {
  Write-Host "  Os textos rapidos nao foram instalados: \$(\$_.Exception.Message)" -ForegroundColor Yellow
  Write-Host '  O app ja esta instalado. Para tentar de novo, baixe o instalador outra vez no site.'
}
PS;
    }

    /** PowerShell do instalador (público para os testes conferirem o conteúdo). */
    public function scriptWindows(string $url, string $icone, string $extra = ''): string
    {
        $url = str_replace("'", "''", $url);
        $icone = str_replace("'", "''", $icone);

        return <<<PS
\$ErrorActionPreference = 'Stop'
\$url = '{$url}'
\$pasta = Join-Path \$env:LOCALAPPDATA 'WorkFlower'
New-Item -ItemType Directory -Force -Path \$pasta | Out-Null
\$ico = Join-Path \$pasta 'work-flower.ico'
try { [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12; Invoke-WebRequest -UseBasicParsing -Uri '{$icone}' -OutFile \$ico } catch { }
\$pf86 = [Environment]::GetEnvironmentVariable('ProgramFiles(x86)')
\$chrome = @("\$env:ProgramFiles\\Google\\Chrome\\Application\\chrome.exe", "\$pf86\\Google\\Chrome\\Application\\chrome.exe", "\$env:LOCALAPPDATA\\Google\\Chrome\\Application\\chrome.exe")
\$edge = @("\$pf86\\Microsoft\\Edge\\Application\\msedge.exe", "\$env:ProgramFiles\\Microsoft\\Edge\\Application\\msedge.exe")
\$padrao = ''
try { \$padrao = (Get-ItemProperty 'HKCU:\\Software\\Microsoft\\Windows\\Shell\\Associations\\UrlAssociations\\https\\UserChoice').ProgId } catch { }
\$ordem = if (\$padrao -like 'MSEdge*') { \$edge + \$chrome } else { \$chrome + \$edge }
\$nav = \$ordem | Where-Object { \$_ -and (Test-Path \$_) } | Select-Object -First 1
\$sh = New-Object -ComObject WScript.Shell
\$atalhos = @((Join-Path ([Environment]::GetFolderPath('Desktop')) 'Work Flower.lnk'), (Join-Path ([Environment]::GetFolderPath('Programs')) 'Work Flower.lnk'))
foreach (\$a in \$atalhos) {
  \$l = \$sh.CreateShortcut(\$a)
  if (\$nav) { \$l.TargetPath = \$nav; \$l.Arguments = "--app=\$url" } else { \$l.TargetPath = "\$env:WINDIR\\explorer.exe"; \$l.Arguments = \$url }
  if (Test-Path \$ico) { \$l.IconLocation = \$ico }
  \$l.Description = 'Work Flower'
  \$l.Save()
}
\$des = "@echo off`r`ndel `"\$(\$atalhos[0])`" 2>nul`r`ndel `"\$(\$atalhos[1])`" 2>nul`r`necho Work Flower removido deste computador.`r`npause`r`nrmdir /s /q `"\$pasta`"`r`n"
Set-Content -Path (Join-Path \$pasta 'Desinstalar Work Flower.cmd') -Value \$des -Encoding ASCII
{$extra}
Write-Host ''
Write-Host '  Pronto! O Work Flower esta na Area de Trabalho e no Menu Iniciar.' -ForegroundColor Green
Write-Host '  Abrindo agora... (se pedir login, marque "Manter conectado" uma vez).'
if (\$nav) { Start-Process -FilePath \$nav -ArgumentList "--app=\$url" } else { Start-Process \$url }
Start-Sleep -Seconds 3
PS;
    }

    /** Base do app sempre com barra no fim (ex.: https://websight.com.br/work-flower/). */
    private function base(): string
    {
        return rtrim(url('/'), '/') . '/';
    }
}
