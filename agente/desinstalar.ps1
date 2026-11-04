<#
    Remove o agente do Work Flower deste usuário: para o agente, tira o início automático
    e apaga a pasta (com o token protegido). Seus scripts são guardados numa cópia, se houver.
    Depois, revogue o token no site (Automações > Revogar) para garantir.
#>
param([string]$Pasta = (Join-Path $env:LOCALAPPDATA 'WorkFlowerAgente'))

$ErrorActionPreference = 'Continue'

# para o agente que estiver rodando com este arquivo
Get-CimInstance Win32_Process -Filter "Name = 'powershell.exe'" |
    Where-Object { $_.CommandLine -like "*$Pasta\agente.ps1*" -and $_.ProcessId -ne $PID } |
    ForEach-Object { Stop-Process -Id $_.ProcessId -Force }

Unregister-ScheduledTask -TaskName 'WorkFlowerAgente' -Confirm:$false -ErrorAction SilentlyContinue
$atalho = Join-Path ([Environment]::GetFolderPath('Startup')) 'Work Flower agente.lnk'
if (Test-Path -LiteralPath $atalho) { Remove-Item -LiteralPath $atalho -Force }

$scripts = Join-Path $Pasta 'scripts'
if ((Test-Path -LiteralPath $scripts) -and (Get-ChildItem -LiteralPath $scripts -Filter *.ps1 | Where-Object Name -ne 'exemplo.ps1')) {
    $copia = Join-Path ([Environment]::GetFolderPath('MyDocuments')) 'WorkFlower-scripts'
    Copy-Item -LiteralPath $scripts -Destination $copia -Recurse -Force
    Write-Host "Seus scripts foram copiados para $copia"
}

if (Test-Path -LiteralPath $Pasta) {
    Set-Location $env:TEMP
    Remove-Item -LiteralPath $Pasta -Recurse -Force
}
Write-Host 'Agente removido. Lembre de revogar o token no site (Automações > Seus computadores > Revogar).'
