# Agente de automações do Work Flower (Windows)

Um site não consegue mexer no seu computador sozinho. Quem faz isso é este **agente**: um script
de PowerShell (já vem no Windows 10 e 11, não precisa instalar nada) que confere o Work Flower a cada
30 segundos, busca as automações pendentes **deste** computador e roda só os tipos de ação que você
deixou.

O que ele sabe fazer:

| Ação no site               | O que acontece no PC                                              |
|----------------------------|-------------------------------------------------------------------|
| Mostrar notificação        | Balão de aviso perto do relógio                                   |
| Abrir site (URL)           | Abre no navegador padrão (só `http://` e `https://`)              |
| Abrir programa ou arquivo  | Abre o `.exe`, PDF, planilha etc. (sem argumentos, sem terminais)  |
| Rodar script salvo no PC   | Roda um `.ps1` da pasta `scripts` **depois de perguntar a você**  |
| Copiar pasta (backup)      | Copia a pasta para `destino\nome-AAAAMMDD-HHMMSS` com o robocopy   |
| Digitar texto              | Espera alguns segundos e digita o texto onde estiver o cursor     |

Gatilhos (configurados no site): horário (com dias da semana), a cada X minutos, quando uma tarefa
é criada ou concluída (em qualquer área ou só numa) e o botão **Rodar agora**.

## Instalação

1. No site, abra o menu da sua conta > **Automações no PC**.
2. Em **Seus computadores**, dê um nome (ex.: "PC de casa") e clique em **Gerar token**.
   Copie o token: ele só aparece uma vez.
3. Clique em **Baixar o agente (.zip)** e extraia o arquivo em qualquer pasta.
4. Clique com o botão direito em `instalar.ps1` > **Executar com o PowerShell**.
   - Confirme o endereço do site (já vem preenchido) e cole o token (ele não aparece na tela).
   - O instalador testa a conexão, copia os arquivos para `%LOCALAPPDATA%\WorkFlowerAgente`,
     faz o agente iniciar junto com o Windows e já o liga.
   - Se o Windows bloquear o script, abra o PowerShell na pasta e rode:
     `powershell -ExecutionPolicy Bypass -File .\instalar.ps1`
5. Um ícone aparece perto do relógio. Com o botão direito nele: **Pausar**, **Abrir registro**,
   **Abrir pasta de scripts** e **Sair**.
6. No site, crie uma automação simples (ex.: notificação) e clique em **Rodar agora**. Em até 30 s
   o aviso aparece, e o histórico do site mostra "Concluída".

Não precisa ser administrador. O agente roda com o seu usuário, e só enquanto você está logado.

## Configuração (`config.json`)

Fica em `%LOCALAPPDATA%\WorkFlowerAgente\config.json`. Depois de mudar, clique em **Sair** no ícone
e abra o agente de novo (ou reinicie o computador).

```json
{
  "site": "https://seu-site",
  "token_protegido": "(token cifrado pelo Windows, não mexa)",
  "intervalo": 30,
  "acoesPermitidas": ["notificacao", "url", "abrir", "script", "backup", "digitar"],
  "pastasPermitidas": [],
  "scriptsSemConfirmar": []
}
```

- **acoesPermitidas**: tire o que você não quer que o site possa pedir. Ex.: só avisos e sites →
  `["notificacao", "url"]`. Uma ação fora da lista é recusada e aparece como "Recusada" no site.
- **pastasPermitidas**: se tiver alguma pasta aqui, "abrir" e "backup" só funcionam dentro delas.
- **scriptsSemConfirmar**: nomes de scripts (sem `.ps1`) que rodam sem perguntar. Deixe vazio se
  não tiver certeza.
- **intervalo**: segundos entre as conferências (mínimo 10).
- **expansor**: `true` liga os textos rápidos no Windows inteiro (veja abaixo).

## Textos rápidos no Windows inteiro

Os atalhos que você cria no site (configurações › **Atalhos** › textos rápidos, ex.: `;ass` vira sua
assinatura) também funcionam em qualquer programa do Windows: e-mail, Word, WhatsApp, navegador, etc.

- **Jeito mais fácil:** no site, logado, clique em **Baixar o app** › **App + textos rápidos**. O
  `Instalar Work Flower.cmd` cria o app e instala este agente com os textos rápidos ligados (o arquivo leva um
  código que vale 30 minutos e uma vez só; o token fica só no seu computador).
- **Quem já tem o agente:** clique com o botão direito no ícone perto do relógio › **Textos rápidos no
  Windows** (liga e desliga). **Atualizar textos rápidos** busca na hora o que você mudou no site (sozinho,
  ele confere a cada 2 minutos). Se a chave do expansor estiver desligada no site, ele fica desligado aqui também.
- Variáveis `{data}`, `{hora}`, `{data_extenso}`, `{dia_semana}`, `{ano}`, `{nome}`, `{primeiro_nome}`,
  `{email}` e `{cursor}` funcionam como no site.
- **Privacidade:** o agente (arquivo `expansor.cs`, compilado pelo próprio Windows ao abrir) guarda só os
  últimos 40 caracteres digitados, na memória, para reconhecer o atalho. Nada é gravado em disco nem enviado.
  Mudar de janela, Enter, Tab, setas e atalhos com Ctrl/Alt zeram essa memória.
- Programas abertos **como administrador** não recebem a troca (o Windows não deixa).

## Scripts

O site **nunca** manda código para o computador: ele manda só o **nome** de um script. O script
precisa existir na pasta `scripts` do agente, criado por você no próprio computador. Antes de rodar,
o agente mostra uma janela pedindo confirmação (a não ser que você tenha colocado o nome em
`scriptsSemConfirmar`). Há um `exemplo.ps1` que só grava a hora num arquivo da Área de Trabalho.
Scripts que passam de 10 minutos são parados.

## Segurança: leia antes de instalar

- **O token dá controle do seu computador** dentro das ações permitidas: quem tiver o token consegue
  abrir sites e programas, digitar texto e pedir scripts (que ainda assim pedem confirmação). Trate
  como uma senha: não mande para ninguém, não publique prints com ele.
- No computador, o token fica **cifrado pelo Windows (DPAPI)**: só o seu usuário, neste computador,
  consegue ler. No site fica só uma impressão (hash) dele; nem a equipe do Work Flower consegue vê-lo.
- **Para revogar**: no site, em **Automações no PC > Seus computadores**, clique em **Revogar**. Na
  hora o token para de funcionar e o que estava esperando aquele computador é cancelado. Faça isso se
  perder o computador, se o token vazar ou se não usar mais o agente.
- O agente não abre terminais nem scripts pela ação "abrir" (`cmd`, `powershell`, `.bat`, `.ps1`,
  `.vbs`, atalhos `.lnk` etc. são recusados, no site e de novo no computador), não aceita argumentos
  para os programas e só fala com o site por `https://`.
- Só existe comunicação de saída (o agente pergunta ao site); nenhuma porta é aberta no computador.
- Tudo é por conta: um computador só recebe as automações da conta que gerou o token.
- O agente guarda um registro em `%LOCALAPPDATA%\WorkFlowerAgente\agente.log` com tudo que rodou.
- Pedidos que esperaram mais de 12 horas (computador desligado) expiram e não rodam mais. Um horário
  perdido dispara uma vez só quando o computador volta.

## Desinstalar

1. Rode `desinstalar.ps1` (fica em `%LOCALAPPDATA%\WorkFlowerAgente`, ou no .zip baixado). Ele para o
   agente, tira o início automático e apaga a pasta. Se você tinha scripts próprios, eles são copiados
   para `Documentos\WorkFlower-scripts`.
2. No site, **revogue** o token do computador.

## Testar sem instalar

```powershell
# confere o site uma vez e sai (precisa de um config.json na mesma pasta ou em -Config)
powershell -ExecutionPolicy Bypass -File .\agente.ps1 -UmaVez -Config C:\caminho\config.json
```

O `config.json` pode ter `"token": "wfa_..."` em texto, em vez de `token_protegido`, só para testes.
