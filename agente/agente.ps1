<#
    Agente do Work Flower para Windows.
    Confere o site a cada N segundos, busca as automações pendentes deste computador e roda
    apenas os tipos de ação permitidos em config.json. Não executa comandos livres vindos do site:
    scripts só rodam se estiverem na pasta "scripts" deste computador, e com confirmação.

    Também faz os textos rápidos da conta (os atalhos do expansor do site) funcionarem em qualquer programa do
    Windows, se "expansor" estiver ligado em config.json ou no menu do ícone (expansor.cs, gancho de teclado).

    Uso:  powershell -ExecutionPolicy Bypass -File agente.ps1            (fica rodando)
          powershell -ExecutionPolicy Bypass -File agente.ps1 -UmaVez    (confere uma vez e sai)
#>
param(
    [switch]$UmaVez,
    [string]$Config = (Join-Path $PSScriptRoot 'config.json')
)

$ErrorActionPreference = 'Stop'
$VERSAO = '1.1.0'
$PASTA = Split-Path -Parent $Config
$LOG = Join-Path $PASTA 'agente.log'
$SCRIPTS = Join-Path $PASTA 'scripts'

Add-Type -AssemblyName System.Windows.Forms, System.Drawing
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12

# mesmas regras do site (conferidas de novo aqui: o computador não confia só no servidor)
$EXT_PROIBIDAS = @('bat','cmd','com','ps1','psm1','vbs','vbe','js','jse','wsf','wsh','hta','msi','scr','reg','pif','cpl','lnk','url')
$PROG_PROIBIDOS = @('cmd.exe','powershell.exe','pwsh.exe','wscript.exe','cscript.exe','mshta.exe','rundll32.exe','regsvr32.exe','reg.exe','bash.exe','wsl.exe','msiexec.exe','certutil.exe','bitsadmin.exe','schtasks.exe')

function Escrever-Log([string]$msg) {
    try {
        if ((Test-Path -LiteralPath $LOG) -and (Get-Item -LiteralPath $LOG).Length -gt 1MB) {
            Move-Item -LiteralPath $LOG -Destination "$LOG.1" -Force
        }
        Add-Content -LiteralPath $LOG -Value ("{0:yyyy-MM-dd HH:mm:ss}  {1}" -f (Get-Date), $msg) -Encoding UTF8
    } catch { }
}

# ------------------------------------------------------------------ configuração
if (-not (Test-Path -LiteralPath $Config)) {
    Write-Host "Arquivo de configuração não encontrado: $Config. Rode instalar.ps1 primeiro."
    exit 1
}
$cfg = Get-Content -LiteralPath $Config -Raw -Encoding UTF8 | ConvertFrom-Json

function Ler-Token {
    if ($cfg.token_protegido) {
        # protegido com DPAPI: só este usuário do Windows, neste computador, consegue ler
        $seguro = ConvertTo-SecureString $cfg.token_protegido
        $bstr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($seguro)
        try { return [Runtime.InteropServices.Marshal]::PtrToStringBSTR($bstr) } finally { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($bstr) }
    }
    return [string]$cfg.token
}

$SITE = ([string]$cfg.site).TrimEnd('/')
$TOKEN = Ler-Token
$INTERVALO = [Math]::Max(10, [int]($(if ($cfg.intervalo) { $cfg.intervalo } else { 30 })))
$PERMITIDAS = @($cfg.acoesPermitidas)
$PASTAS_OK = @($cfg.pastasPermitidas | Where-Object { $_ })
$SEM_CONFIRMAR = @($cfg.scriptsSemConfirmar | Where-Object { $_ })

$uri = [Uri]$SITE
if ($uri.Scheme -ne 'https' -and $uri.Host -notin @('localhost', '127.0.0.1', '::1')) {
    Write-Host 'O endereço do site precisa ser https:// (o token não pode viajar sem criptografia).'
    exit 1
}

# só uma cópia do agente por usuário
$mutex = New-Object Threading.Mutex($false, 'Local\WorkFlowerAgente')
if (-not $mutex.WaitOne(0)) {
    if (-not $UmaVez) { Write-Host 'O agente já está rodando.'; exit 0 }
}

# ------------------------------------------------------------------ API
function Chamar-Api([string]$metodo, [string]$caminho, $corpo = $null) {
    $req = @{
        Uri = "$SITE/api/agente/$caminho"; Method = $metodo; UseBasicParsing = $true; TimeoutSec = 30
        Headers = @{ Authorization = "Bearer $TOKEN"; Accept = 'application/json'; 'X-Agente-Versao' = $VERSAO }
    }
    if ($null -ne $corpo) {
        $req.ContentType = 'application/json; charset=utf-8'
        $req.Body = [Text.Encoding]::UTF8.GetBytes(($corpo | ConvertTo-Json -Depth 5 -Compress))
    }
    $r = Invoke-WebRequest @req
    # lê como UTF-8 (o PowerShell 5 erraria os acentos)
    $ms = New-Object IO.MemoryStream
    $r.RawContentStream.Position = 0
    $r.RawContentStream.CopyTo($ms)
    return [Text.Encoding]::UTF8.GetString($ms.ToArray()) | ConvertFrom-Json
}

# ------------------------------------------------------------------ ícone na bandeja
$icone = New-Object Windows.Forms.NotifyIcon
$icone.Icon = [Drawing.SystemIcons]::Application
$icone.Text = 'Work Flower - agente de automações'
$icone.Visible = $true
$script:pausado = $false
$script:sair = $false
$menu = New-Object Windows.Forms.ContextMenuStrip
$itemPausar = $menu.Items.Add('Pausar')
$itemPausar.add_Click({ $script:pausado = -not $script:pausado; $itemPausar.Text = $(if ($script:pausado) { 'Continuar' } else { 'Pausar' }) })
$itemExpansor = New-Object Windows.Forms.ToolStripMenuItem('Textos rápidos no Windows')
$itemExpansor.add_Click({ Alternar-Expansor })
$menu.Items.Add($itemExpansor) | Out-Null
$menu.Items.Add('Atualizar textos rápidos').add_Click({ $script:expProxima = Get-Date }) | Out-Null
$menu.Items.Add('Abrir registro (log)').add_Click({ if (Test-Path -LiteralPath $LOG) { Start-Process notepad.exe -ArgumentList "`"$LOG`"" } }) | Out-Null
$menu.Items.Add('Abrir pasta de scripts').add_Click({ Start-Process explorer.exe -ArgumentList "`"$SCRIPTS`"" }) | Out-Null
$menu.Items.Add('Sair').add_Click({ $script:sair = $true }) | Out-Null
$icone.ContextMenuStrip = $menu

function Avisar([string]$titulo, [string]$texto) {
    if (-not $titulo) { $titulo = 'Work Flower' }
    if (-not $texto) { $texto = ' ' }
    $icone.ShowBalloonTip(10000, $titulo, $texto, [Windows.Forms.ToolTipIcon]::Info)
}

function Perguntar([string]$texto) {
    $dono = New-Object Windows.Forms.Form -Property @{ TopMost = $true; ShowInTaskbar = $false; WindowState = 'Minimized' }
    try {
        return [Windows.Forms.MessageBox]::Show($dono, $texto, 'Work Flower - confirmar', 'YesNo', 'Question', 'Button2') -eq 'Yes'
    } finally { $dono.Dispose() }
}

# ------------------------------------------------------------------ ações
function Caminho-Proibido([string]$c) {
    $base = [IO.Path]::GetFileName($c.Trim()).ToLower()
    $ext = [IO.Path]::GetExtension($base).TrimStart('.')
    return ($PROG_PROIBIDOS -contains $base) -or ($PROG_PROIBIDOS -contains "$base.exe") -or ($EXT_PROIBIDAS -contains $ext)
}

function Dentro-Das-Pastas([string]$c) {
    if ($PASTAS_OK.Count -eq 0) { return $true }
    $cheio = [IO.Path]::GetFullPath($c).TrimEnd('\') + '\'
    foreach ($p in $PASTAS_OK) {
        if ($cheio.StartsWith(([IO.Path]::GetFullPath($p).TrimEnd('\') + '\'), [StringComparison]::OrdinalIgnoreCase)) { return $true }
    }
    return $false
}

function Escapar-SendKeys([string]$t) {
    $sb = New-Object Text.StringBuilder
    foreach ($ch in $t.Replace("`r", '').ToCharArray()) {
        if ('+^%~(){}[]'.Contains([string]$ch)) { [void]$sb.Append('{' + $ch + '}') }
        elseif ($ch -eq "`n") { [void]$sb.Append('{ENTER}') }
        else { [void]$sb.Append($ch) }
    }
    return $sb.ToString()
}

# devolve o texto do resultado; lança 'RECUSADA:' para recusas e erro comum para falhas
function Rodar-Acao($a) {
    $tipo = [string]$a.tipo
    if ($PERMITIDAS -notcontains $tipo) { throw "RECUSADA: a ação '$tipo' não está liberada neste computador (config.json)." }

    switch ($tipo) {
        'notificacao' {
            Avisar ([string]$a.titulo) ([string]$a.mensagem)
            return "Notificação mostrada: $($a.titulo)"
        }
        'url' {
            $u = [string]$a.url
            if ($u -notmatch '^https?://\S+$') { throw "RECUSADA: endereço inválido." }
            Start-Process $u
            return "Site aberto: $u"
        }
        'abrir' {
            $c = [Environment]::ExpandEnvironmentVariables(([string]$a.caminho).Trim('"', ' '))
            if (Caminho-Proibido $c) { throw "RECUSADA: '$c' é terminal ou script; não pode ser aberto por aqui." }
            if (-not (Dentro-Das-Pastas $c)) { throw "RECUSADA: '$c' está fora das pastas permitidas." }
            if (-not (Test-Path -LiteralPath $c)) { throw "Não encontrei '$c'." }
            Start-Process -FilePath $c
            return "Aberto: $c"
        }
        'script' {
            $nome = [string]$a.nome
            if ($nome -notmatch '^[A-Za-z0-9][A-Za-z0-9 _.-]{0,59}$' -or $nome.Contains('..')) { throw "RECUSADA: nome de script inválido." }
            $arq = Join-Path $SCRIPTS "$nome.ps1"
            if (-not (Test-Path -LiteralPath $arq)) { throw "RECUSADA: o script '$nome.ps1' não existe na pasta $SCRIPTS." }
            if ($SEM_CONFIRMAR -notcontains $nome) {
                if (-not (Perguntar "O Work Flower quer rodar o script:`n`n$arq`n`nPermitir agora?")) { throw "RECUSADA: você não permitiu rodar '$nome'." }
            }
            $p = Start-Process powershell.exe -ArgumentList @('-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', "`"$arq`"") -PassThru -WindowStyle Hidden
            if (-not $p.WaitForExit(600000)) { $p.Kill(); throw "O script '$nome' passou de 10 minutos e foi parado." }
            if ($p.ExitCode -ne 0) { throw "O script '$nome' terminou com código $($p.ExitCode)." }
            return "Script '$nome' rodou (código 0)."
        }
        'backup' {
            $de = [Environment]::ExpandEnvironmentVariables(([string]$a.origem).Trim('"', ' '))
            $para = [Environment]::ExpandEnvironmentVariables(([string]$a.destino).Trim('"', ' '))
            if (-not (Test-Path -LiteralPath $de -PathType Container)) { throw "A pasta de origem '$de' não existe." }
            if (-not (Dentro-Das-Pastas $de) -or -not (Dentro-Das-Pastas $para)) { throw "RECUSADA: origem ou destino fora das pastas permitidas." }
            $deCheio = [IO.Path]::GetFullPath($de).TrimEnd('\') + '\'
            if (([IO.Path]::GetFullPath($para).TrimEnd('\') + '\').StartsWith($deCheio, [StringComparison]::OrdinalIgnoreCase)) { throw "O destino não pode ficar dentro da origem." }
            $alvo = Join-Path $para ("{0}-{1:yyyyMMdd-HHmmss}" -f (Split-Path $de -Leaf), (Get-Date))
            New-Item -ItemType Directory -Path $alvo -Force | Out-Null
            & robocopy.exe $de $alvo /E /R:1 /W:1 /NFL /NDL /NJH /NJS /NP | Out-Null
            if ($LASTEXITCODE -ge 8) { throw "O robocopy falhou (código $LASTEXITCODE)." }
            return "Cópia feita em $alvo"
        }
        'digitar' {
            $espera = [Math]::Min(30, [Math]::Max(0, [int]$a.espera))
            Start-Sleep -Seconds $espera
            [Windows.Forms.SendKeys]::SendWait((Escapar-SendKeys ([string]$a.texto)))
            return "Texto digitado ($(([string]$a.texto).Length) caracteres)."
        }
        default { throw "RECUSADA: ação desconhecida '$tipo'." }
    }
}

function Rodar-Execucao($e) {
    Escrever-Log "Rodando #$($e.id) '$($e.nome)' ($($e.origem))"
    $linhas = @(); $status = 'ok'
    $acoes = $e.acoes
    if ($e.acoes_b64) {
        # versão em base64 (JSON UTF-8): chega intacta mesmo com filtros de hospedagem no caminho
        $acoes = [Text.Encoding]::UTF8.GetString([Convert]::FromBase64String([string]$e.acoes_b64)) | ConvertFrom-Json
    }
    foreach ($a in @($acoes)) {
        try {
            $linhas += Rodar-Acao $a
        } catch {
            $msg = $_.Exception.Message
            if ($msg.StartsWith('RECUSADA: ')) { $status = 'recusada'; $msg = $msg.Substring(10) } else { $status = 'erro' }
            $linhas += $msg
            break   # não continua depois de uma falha
        }
    }
    $texto = ($linhas -join "`n")
    Escrever-Log "  -> $status : $($texto -replace "`n", ' | ')"
    try {
        # o texto vai em base64: erros com caminhos e comandos fazem o mod_security responder 406
        $b64 = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($texto))
        Chamar-Api 'POST' "execucoes/$($e.id)" @{ status = $status; resultado_b64 = $b64 } | Out-Null
    } catch { Escrever-Log "  (não consegui avisar o site: $($_.Exception.Message))" }
}

function Conferir {
    $r = Chamar-Api 'GET' 'pendentes'
    foreach ($e in @($r.execucoes)) { if ($e) { Rodar-Execucao $e } }
}

# ------------------------------------------------------------------ textos rápidos no Windows inteiro
# Os atalhos vêm da conta (/api/agente/trechos, os mesmos do expansor do site) e são conferidos a cada 2 min.
# O gancho de teclado (expansor.cs) só guarda os últimos 40 caracteres em memória; nada é gravado nem enviado.
$script:expLocal = ($cfg.PSObject.Properties.Name -contains 'expansor') -and [bool]$cfg.expansor
$script:expSite = $true
$script:expVersao = ''
$script:expProxima = Get-Date
$script:expPronto = $false

function Carregar-Expansor {
    if ($script:expPronto) { return $true }
    $cs = Join-Path $PSScriptRoot 'expansor.cs'
    if (-not (Test-Path -LiteralPath $cs)) { Escrever-Log 'Textos rápidos: expansor.cs não encontrado (baixe o agente de novo).'; return $false }
    try {
        if (-not ('WFExpansor' -as [type])) { Add-Type -Path $cs }
        $script:expPronto = $true
    } catch { Escrever-Log "Textos rápidos: não deu para carregar ($($_.Exception.Message))." }
    return $script:expPronto
}

function Aplicar-Expansor {
    $ligar = $script:expLocal -and $script:expSite
    $itemExpansor.Checked = $script:expLocal
    $itemExpansor.Text = $(if (-not $script:expLocal) { 'Textos rápidos no Windows: desligados' }
        elseif (-not $script:expSite) { 'Textos rápidos no Windows: desligados no site' }
        else { 'Textos rápidos no Windows: ligados' })
    if (-not $script:expPronto) { return }
    [WFExpansor]::Ligado = $ligar
    if ($ligar -and -not [WFExpansor]::Rodando) { [WFExpansor]::Iniciar() }
}

function Atualizar-Trechos {
    $script:expProxima = (Get-Date).AddMinutes(2)
    if (-not $script:expLocal) { Aplicar-Expansor; return }
    if (-not (Carregar-Expansor)) { return }
    $r = Chamar-Api 'GET' 'trechos'
    if ($r.versao -ne $script:expVersao) {
        $lista = @()
        if ($r.trechos_b64) {
            $json = [Text.Encoding]::UTF8.GetString([Convert]::FromBase64String([string]$r.trechos_b64))
            $lido = $json | ConvertFrom-Json
            $lista = @($lido | ForEach-Object { $_ } | Where-Object { $_ -and $_.atalho })
        }
        [string[]]$atalhos = @($lista | ForEach-Object { [string]$_.atalho })
        [string[]]$textos = @($lista | ForEach-Object { [string]$_.texto })
        [WFExpansor]::Definir($atalhos, $textos, [string]$r.nome, [string]$r.email)
        $script:expVersao = [string]$r.versao
        Escrever-Log "Textos rápidos: $($lista.Count) atalhos carregados$(if (-not $r.ligado) { ' (desligados no site)' })."
    }
    $script:expSite = [bool]$r.ligado
    Aplicar-Expansor
}

function Alternar-Expansor {
    $script:expLocal = -not $script:expLocal
    try {
        # guarda a escolha em config.json (o resto da configuração fica como está)
        $c = Get-Content -LiteralPath $Config -Raw -Encoding UTF8 | ConvertFrom-Json
        $c | Add-Member -NotePropertyName expansor -NotePropertyValue $script:expLocal -Force
        [IO.File]::WriteAllText($Config, ($c | ConvertTo-Json -Depth 4), (New-Object Text.UTF8Encoding($false)))
    } catch { Escrever-Log "Não consegui salvar a escolha em config.json: $($_.Exception.Message)" }
    if ($script:expLocal) {
        Avisar 'Work Flower' 'Textos rápidos ligados: seus atalhos do site agora funcionam em qualquer programa.'
        $script:expVersao = ''; $script:expProxima = Get-Date
    } else {
        Avisar 'Work Flower' 'Textos rápidos desligados neste computador.'
    }
    Aplicar-Expansor
}
Aplicar-Expansor

# ------------------------------------------------------------------ laço principal
Escrever-Log "Agente $VERSAO iniciado ($SITE, a cada $INTERVALO s)."
try {
    $ola = Chamar-Api 'GET' 'ola'
    Escrever-Log "Conectado como '$($ola.dispositivo)' de $($ola.usuario)."
} catch { Escrever-Log "Sem conexão com o site: $($_.Exception.Message)" }

$falhas = 0
try {
    do {
        if ((Get-Date) -ge $script:expProxima -and -not $UmaVez) {
            try { Atualizar-Trechos } catch { Escrever-Log "Textos rápidos: falha ao atualizar ($($_.Exception.Message))." }
        }
        if (-not $script:pausado) {
            try { Conferir; $falhas = 0 }
            catch {
                $falhas++
                $cod = $null; try { $cod = [int]$_.Exception.Response.StatusCode } catch { }
                Escrever-Log "Falha ao conferir: $($_.Exception.Message)"
                if ($cod -eq 401) {
                    Avisar 'Work Flower' 'O token deste computador foi revogado ou é inválido. Rode instalar.ps1 com um token novo.'
                    break
                }
            }
        }
        if ($UmaVez) { break }
        # espera com o ícone respondendo; depois de falhas seguidas espera mais (até 5 min)
        $espera = [Math]::Min(300, $INTERVALO * [Math]::Pow(2, [Math]::Min($falhas, 4)))
        $fim = (Get-Date).AddSeconds($espera)
        while ((Get-Date) -lt $fim -and -not $script:sair -and ((Get-Date) -lt $script:expProxima -or $UmaVez)) { [Windows.Forms.Application]::DoEvents(); Start-Sleep -Milliseconds 200 }
    } while (-not $script:sair)
} finally {
    if ($UmaVez) { Start-Sleep -Seconds 3; [Windows.Forms.Application]::DoEvents() }  # deixa a notificação aparecer
    if ($script:expPronto) { try { [WFExpansor]::Parar() } catch { } }
    $icone.Visible = $false
    $icone.Dispose()
    Escrever-Log 'Agente encerrado.'
}
