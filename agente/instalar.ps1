<#
    Instala o agente do Work Flower para o usuário atual do Windows (não precisa ser administrador).
    - Copia os arquivos para %LOCALAPPDATA%\WorkFlowerAgente
    - Guarda o token protegido pelo Windows (DPAPI: só este usuário neste computador consegue ler)
    - Faz o agente iniciar junto com o Windows (tarefa agendada; se não der, atalho em Inicializar)

    Uso: clique com o botão direito > "Executar com o PowerShell"
     ou: powershell -ExecutionPolicy Bypass -File instalar.ps1
#>
param(
    [string]$Site,
    [string]$Token,
    [string]$Destino = (Join-Path $env:LOCALAPPDATA 'WorkFlowerAgente'),
    [int]$Intervalo = 30,
    [switch]$SemAutoInicio,
    [switch]$SemIniciar
)

$ErrorActionPreference = 'Stop'
$origem = $PSScriptRoot
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12

Write-Host ''
Write-Host '=== Work Flower - instalação do agente de automações ===' -ForegroundColor Cyan
Write-Host ''

# ---------------------------------------------------------------- endereço do site
if (-not $Site) {
    $padrao = ''
    $txt = Join-Path $origem 'site.txt'
    if (Test-Path -LiteralPath $txt) { $padrao = (Get-Content -LiteralPath $txt -Raw).Trim() }
    $Site = Read-Host "Endereço do site [$padrao]"
    if (-not $Site) { $Site = $padrao }
}
$Site = $Site.Trim().TrimEnd('/')
$u = [Uri]$Site
if ($u.Scheme -ne 'https' -and $u.Host -notin @('localhost', '127.0.0.1', '::1')) {
    throw 'O endereço precisa começar com https:// (o token não pode viajar sem criptografia).'
}

# ---------------------------------------------------------------- token (não aparece na tela)
if (-not $Token) {
    Write-Host 'No site, abra Automações > Seus computadores > Gerar token, e cole aqui.'
    $seguro = Read-Host 'Token do computador' -AsSecureString
    $bstr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($seguro)
    try { $Token = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($bstr) } finally { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($bstr) }
}
$Token = $Token.Trim()
if (-not $Token.StartsWith('wfa_')) { throw 'Esse não parece um token do Work Flower (começa com wfa_).' }

Write-Host 'Testando a conexão...'
try {
    $r = Invoke-WebRequest -Uri "$Site/api/agente/ola" -UseBasicParsing -TimeoutSec 30 -Headers @{ Authorization = "Bearer $Token"; Accept = 'application/json' }
    $ms = New-Object IO.MemoryStream; $r.RawContentStream.CopyTo($ms)
    $ola = [Text.Encoding]::UTF8.GetString($ms.ToArray()) | ConvertFrom-Json
    Write-Host "Conectado: computador '$($ola.dispositivo)' da conta de $($ola.usuario)." -ForegroundColor Green
} catch {
    throw "Não consegui conectar com esse token: $($_.Exception.Message)"
}

# ---------------------------------------------------------------- arquivos
New-Item -ItemType Directory -Path $Destino -Force | Out-Null
New-Item -ItemType Directory -Path (Join-Path $Destino 'scripts') -Force | Out-Null
foreach ($f in @('agente.ps1', 'desinstalar.ps1', 'README.md')) {
    if (Test-Path -LiteralPath (Join-Path $origem $f)) { Copy-Item -LiteralPath (Join-Path $origem $f) -Destination $Destino -Force }
}
# scripts de exemplo: não sobrescreve os que você já editou
Get-ChildItem -LiteralPath (Join-Path $origem 'scripts') -Filter *.ps1 -ErrorAction SilentlyContinue | ForEach-Object {
    $alvo = Join-Path (Join-Path $Destino 'scripts') $_.Name
    if (-not (Test-Path -LiteralPath $alvo)) { Copy-Item -LiteralPath $_.FullName -Destination $alvo }
}

$cfgArq = Join-Path $Destino 'config.json'
$antigo = $null
if (Test-Path -LiteralPath $cfgArq) { $antigo = Get-Content -LiteralPath $cfgArq -Raw -Encoding UTF8 | ConvertFrom-Json }

$cfg = [ordered]@{
    site                = $Site
    token_protegido     = (ConvertTo-SecureString $Token -AsPlainText -Force | ConvertFrom-SecureString)
    intervalo           = [Math]::Max(10, $Intervalo)
    # tire daqui o que não quer que o site possa pedir
    acoesPermitidas     = $(if ($antigo) { @($antigo.acoesPermitidas) } else { @('notificacao', 'url', 'abrir', 'script', 'backup', 'digitar') })
    # se tiver alguma pasta aqui, "abrir" e "backup" só funcionam dentro delas
    pastasPermitidas    = $(if ($antigo) { @($antigo.pastasPermitidas) } else { @() })
    # scripts que rodam sem perguntar (por padrão, todos perguntam)
    scriptsSemConfirmar = $(if ($antigo) { @($antigo.scriptsSemConfirmar) } else { @() })
}
[IO.File]::WriteAllText($cfgArq, ($cfg | ConvertTo-Json -Depth 4), (New-Object Text.UTF8Encoding($false)))
Write-Host "Arquivos em $Destino"

# ---------------------------------------------------------------- iniciar com o Windows
$agente = Join-Path $Destino 'agente.ps1'
$argumentos = "-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File `"$agente`""
if (-not $SemAutoInicio) {
    $ok = $false
    try {
        $acao = New-ScheduledTaskAction -Execute 'powershell.exe' -Argument $argumentos
        $gat = New-ScheduledTaskTrigger -AtLogOn -User "$env:USERDOMAIN\$env:USERNAME"
        $conf = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -ExecutionTimeLimit ([TimeSpan]::Zero) -MultipleInstances IgnoreNew
        $princ = New-ScheduledTaskPrincipal -UserId "$env:USERDOMAIN\$env:USERNAME" -LogonType Interactive -RunLevel Limited
        Register-ScheduledTask -TaskName 'WorkFlowerAgente' -Action $acao -Trigger $gat -Settings $conf -Principal $princ -Description 'Agente de automações do Work Flower' -Force | Out-Null
        $ok = $true
        Write-Host 'Inicia junto com o Windows (tarefa agendada "WorkFlowerAgente").'
    } catch {
        Write-Host "Tarefa agendada não deu ($($_.Exception.Message)); usando a pasta Inicializar."
    }
    if (-not $ok) {
        $atalho = Join-Path ([Environment]::GetFolderPath('Startup')) 'Work Flower agente.lnk'
        $sh = New-Object -ComObject WScript.Shell
        $l = $sh.CreateShortcut($atalho)
        $l.TargetPath = 'powershell.exe'; $l.Arguments = $argumentos; $l.WorkingDirectory = $Destino; $l.WindowStyle = 7
        $l.Save()
        Write-Host "Inicia junto com o Windows (atalho em $atalho)."
    }
}

if (-not $SemIniciar) {
    Start-Process powershell.exe -ArgumentList $argumentos -WindowStyle Hidden
    Write-Host 'Agente iniciado: procure o ícone perto do relógio.' -ForegroundColor Green
}
Write-Host ''
Write-Host 'Pronto. Para desligar de vez: rode desinstalar.ps1 e revogue o token no site.'
