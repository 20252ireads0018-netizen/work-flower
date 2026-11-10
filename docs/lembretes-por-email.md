# Lembretes por e-mail: o que configurar no cPanel

O código já está pronto. O lembrete de um evento do calendário (com "Lembrete" e a caixinha ✉ ligada) é mandado pelo
comando `calendario:lembrar`, que o agendador do Laravel roda a cada minuto (`routes/console.php`). No servidor faltam
duas coisas: **um servidor de e-mail (SMTP)** e **o cron** que acorda o agendador. Sem elas, os lembretes continuam
aparecendo no navegador e o cartão "Lembretes por e-mail" do quadro mostra um aviso amarelo (sem detalhes técnicos).

Onde a pessoa usa: cartão **Lembretes por e-mail** no quadro (menu "+ Bloco" de qualquer área) e o calendário do painel.
No cartão dá para ver os próximos lembretes, ligar/desligar o e-mail de cada um (ou de todos), criar um lembrete a partir
de um cartão da aba, arrastar um lembrete para o quadro (vira cartão de texto) e mandar um lembrete de teste para si.

## 1. Criar a caixa que envia (cPanel › Contas de e-mail)

1. cPanel › **Contas de e-mail** › **+ Criar**.
2. Endereço sugerido: `lembretes@websight.com.br` (ou `nao-responda@...`). Anote a senha.
3. Em **Connect Devices / Configurar cliente de e-mail**, anote o **servidor de saída (SMTP)**. Na HostGator costuma ser
   `mail.websight.com.br` (ou o nome do servidor, ex.: `br123.hostgator.com.br`), porta **465** com SSL.

> Se o e-mail do domínio é da **Titan** (e não do cPanel), use `smtp.titan.email`, porta 465, com a caixa criada no painel da Titan.

## 2. Colocar o SMTP no `.env` do servidor (cPanel › Gerenciador de arquivos)

Abra o `.env` na raiz do projeto (pasta acima do `public_html/work-flower`, onde está o `artisan`) e troque o bloco `MAIL_`:

```
MAIL_MAILER=smtp
MAIL_SCHEME=smtps
MAIL_HOST=mail.websight.com.br
MAIL_PORT=465
MAIL_USERNAME=lembretes@websight.com.br
MAIL_PASSWORD=a-senha-da-caixa
MAIL_FROM_ADDRESS=lembretes@websight.com.br
MAIL_FROM_NAME="Work Flower"
```

- `MAIL_FROM_ADDRESS` precisa ser **igual** ao `MAIL_USERNAME` (senão o servidor recusa ou o e-mail cai no spam).
- Senha com `#`, espaço ou `"`: coloque entre aspas, ex.: `MAIL_PASSWORD="minha#senha"`.
- Se o site usa cache de configuração, limpe depois de salvar (a mesma rota/ferramenta de limpar cache usada no deploy).

**Conferir:** entre com a conta da administração e abra **Negócios › E-mails** › **Testar envio do site** ›
"Enviar e-mail de teste para mim". Ele mostra o erro exato do SMTP se algo estiver errado. Qualquer pessoa também pode usar
o botão **Testar** no cartão Lembretes, que manda um lembrete de exemplo para o próprio e-mail.

## 3. Criar o cron (cPanel › Trabalhos Cron)

1. cPanel › **Trabalhos Cron** (Cron Jobs).
2. Em "E-mail do cron" deixe vazio (senão chega um e-mail por minuto).
3. **Configurações comuns:** "Uma vez por minuto" (`* * * * *`).
4. **Comando** (troque o caminho pelo da conta; o da HostGator costuma ser `/home2/USUARIO/...` ou `/home/USUARIO/...`):

```
/usr/local/bin/php /home/USUARIO/work-flower/artisan schedule:run >> /dev/null 2>&1
```

- Para descobrir o caminho: no Gerenciador de arquivos, abra a pasta onde está o arquivo `artisan`; o caminho aparece no topo.
- Para descobrir o PHP certo: cPanel › **Selecionar versão do PHP** (ou MultiPHP). Se a conta usa PHP 8.3 do EA-PHP, o
  comando pode ser `/opt/cpanel/ea-php83/root/usr/bin/php`. O Work Flower precisa de PHP 8.3 ou mais novo.

O mesmo cron também roda o backup diário no Google Drive / bucket (03:30) e a leitura das caixas de e-mail (a cada 15 min).

**Conferir:** depois de 2 ou 3 minutos, **Negócios › E-mails › Testar envio do site** mostra "calendário: rodou há X min".
No cartão Lembretes, o aviso fica verde: "Os lembretes com ✉ chegam em ...".

## 4. Se não chegar

| Sintoma | O que fazer |
|---|---|
| Teste diz "Envio simulado" | O `.env` ainda está com `MAIL_MAILER=log`. Confira o passo 2 e limpe o cache de configuração. |
| "Connection refused" / "timed out" | A HostGator bloqueia SMTP externo em alguns planos: use o servidor do próprio cPanel (`mail.seudominio`), porta 465. |
| "Authentication failed" | Usuário é o e-mail completo; confira a senha (crie de novo no cPanel se precisar). |
| E-mail vai para o spam | No cPanel › **Entregabilidade de e-mail**, clique em "Reparar" no SPF e no DKIM do domínio. |
| Cartão diz que o envio automático parou | O cron não está rodando: confira o caminho do PHP e do `artisan` no passo 3. |

Lembretes com mais de 30 minutos de atraso não são mandados (para não chegar uma enxurrada depois de o cron ficar parado).
