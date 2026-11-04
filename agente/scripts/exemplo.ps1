# Script de exemplo do agente do Work Flower.
# Coloque seus scripts nesta pasta (scripts) com a extensão .ps1 e use o nome deles
# (sem o .ps1) na ação "Rodar script salvo no PC" do site. O agente pergunta antes de rodar,
# a não ser que o nome esteja em "scriptsSemConfirmar" no config.json.
#
# Este exemplo só grava a data e hora num arquivo de texto na sua Área de Trabalho.

$arquivo = Join-Path ([Environment]::GetFolderPath('Desktop')) 'work-flower-exemplo.txt'
Add-Content -LiteralPath $arquivo -Value ("Script de exemplo rodou em {0:dd/MM/yyyy HH:mm:ss}" -f (Get-Date))
exit 0
