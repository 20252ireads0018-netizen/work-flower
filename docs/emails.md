# Negócios › E-mails

Página `/negocios/emails` (atalho no card "📧 E-mails" da aba **Loja e marketplaces** da área Negócios).
A pessoa conecta a própria caixa, o site lê as mensagens dos últimos 30 dias, separa por categoria e mostra com busca.
Responder abre o programa de e-mail da pessoa (`mailto:`); o site não envia nem apaga nada.

## Como funciona

- **IMAP próprio, em PHP puro** (`app/Support/Emails/ImapCliente.php`): `stream_socket_client` + openssl, sem a extensão
  `imap` (que não existe no PHP local e saiu do núcleo no PHP 8.4) e sem biblioteca do composer. Abre a pasta com
  `EXAMINE` (somente leitura) e busca com `BODY.PEEK`, então nada fica marcado como lido.
- Servidores prontos na tela: **Titan** `imap.titan.email:993` SSL, **Gmail** `imap.gmail.com:993` (senha de app),
  cPanel (`mail.dominio`), ou outro. Só portas 993 (SSL) e 143 (STARTTLS). Servidores que resolvem para IP interno são recusados.
- Senha, usuário e tokens ficam na tabela `email_contas` com cast `encrypted` (APP_KEY) e em `$hidden`; a tela nunca recebe.
  Trocar a APP_KEY invalida as senhas guardadas (a pessoa conecta de novo).
- Até 3 caixas por conta. Guarda no máximo 60 dias / 1000 mensagens por caixa (só cabeçalho e um trecho de ~220 caracteres).
- **Categorias** (`Classificador`): regras editáveis por pessoa (remetente/assunto/qualquer contém termo, sem acento),
  a primeira que bate vale; quem está na lista de Clientes da área Negócios (e-mail no campo contato) vai para Clientes;
  o resto fica em Outros. A pessoa pode fixar a categoria de uma mensagem à mão. Iniciais: Clientes, Financeiro, Promoções.
- **Sincronização**: botão "Buscar e-mails" e o comando `emails:sincronizar`, agendado a cada 15 minutos em `routes/console.php`.
  Caixas com erro de senha ficam fora do agendador até a pessoa conectar de novo (`--todas` força).
- **Gmail pelo Google (opcional)**: `app/Support/Emails/Google.php` + rotas `/negocios/emails/google` e `/google/retorno`,
  lendo pela Gmail API (escopo `gmail.readonly`). Fica escondido enquanto `GOOGLE_CLIENT_ID`/`GOOGLE_CLIENT_SECRET` estiverem vazios.

## Testar o envio do site (lembretes do calendário)

Só para a conta da administração (`User::ADMINS`): botão **Testar envio do site** na mesma tela. Mostra o envio configurado
(MAIL_MAILER, servidor, porta, segurança e remetente, nunca usuário nem senha), quando o cron rodou por último
(`calendario:lembrar` e `emails:sincronizar` gravam um batimento no cache, `App\Support\Agendador`) e o botão
**Enviar e-mail de teste para mim**, que manda para o e-mail da própria conta e mostra o erro exato do SMTP (com usuário e senha trocados por ***).
Com `MAIL_MAILER=log` avisa que o envio é só simulado. Para a Titan: `MAIL_MAILER=smtp`, `MAIL_HOST=smtp.titan.email`,
`MAIL_PORT=465`, `MAIL_SCHEME=smtps`, `MAIL_USERNAME`/`MAIL_PASSWORD` da caixa e `MAIL_FROM_ADDRESS` igual ao usuário.

## Cuidados que valem para a hospedagem

- **mod_security (406)**: os formulários desta tela vão como `{"b64": base64(JSON)}` e o controlador abre antes de validar,
  porque senhas e regras com aspas/ponto e vírgula parecem SQL para o filtro da HostGator.
- **Limites de pedidos**: cada rota tem prefixo próprio no throttle (`throttle:10,1,emails-conectar`). Sem o prefixo,
  o Laravel conta TODAS as rotas `throttle:N,M` da pessoa no mesmo contador, e um limite baixo estoura por causa de outras telas.

## Para publicar na HostGator (sem shell nem composer)

Não precisa subir nada de `vendor/`: as classes novas carregam pelo PSR-4 que já está no autoload.

Arquivos a enviar:

```
app/Console/Commands/SincronizarEmails.php
app/Console/Commands/LembrarEventos.php   (grava quando o cron rodou)
app/Mail/TesteEnvio.php
app/Support/Agendador.php
resources/views/emails/teste-envio-texto.blade.php
app/Http/Controllers/EmailController.php
app/Models/EmailConta.php
app/Models/EmailMensagem.php
app/Models/EmailRegra.php
app/Support/Emails/            (Classificador, EmailErro, Google, ImapCliente, LeitorEmails, LeitorPadrao, Mime, Sincronizar)
config/services.php            (bloco "google")
database/migrations/2026_11_06_000000_create_emails_tables.php
public/js/emails.js            (vai para a pasta pública/docroot, como os outros JS)
resources/views/areas/negocios/emails.blade.php
resources/views/areas/negocios/partials/secoes.blade.php
routes/emails.php
routes/web.php                 (só a linha final: require __DIR__ . '/emails.php';)
routes/console.php             (só a linha do emails:sincronizar)
```

Depois:

1. Criar as tabelas: rodar a migração (pela rota/ferramenta que o deploy já usa) ou o SQL abaixo no phpMyAdmin.
2. Limpar o cache de rotas/config/views, se o servidor usa `route:cache`/`config:cache`.
3. Cron do cPanel (o mesmo dos lembretes do calendário; se já existe, nada a fazer):
   `* * * * * php /caminho/do/projeto/artisan schedule:run >> /dev/null 2>&1`
4. Conferir que a hospedagem deixa sair conexão para a porta 993: conectar uma caixa Titan pela tela.
   Se der "Não consegui conectar em imap.titan.email:993", o firewall da HostGator está bloqueando saída nessa porta
   e é preciso pedir a liberação ao suporte. A PHP precisa da extensão `openssl` (padrão na HostGator).

SQL equivalente (MySQL):

```sql
CREATE TABLE email_contas (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  tipo VARCHAR(10) NOT NULL DEFAULT 'imap',
  endereco VARCHAR(190) NOT NULL,
  host VARCHAR(190) NULL, porta SMALLINT UNSIGNED NULL, seguranca VARCHAR(10) NULL,
  usuario VARCHAR(190) NULL, senha TEXT NULL, access_token TEXT NULL, refresh_token TEXT NULL,
  token_expira_em TIMESTAMP NULL, pasta VARCHAR(120) NOT NULL DEFAULT 'INBOX', uidvalidity VARCHAR(40) NULL,
  sincronizado_em TIMESTAMP NULL, erro VARCHAR(300) NULL, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  CONSTRAINT email_contas_user_id_foreign FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE email_mensagens (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL, conta_id BIGINT UNSIGNED NOT NULL,
  uid VARCHAR(100) NOT NULL, message_id VARCHAR(250) NULL, de_nome VARCHAR(190) NULL, de_email VARCHAR(190) NULL,
  assunto VARCHAR(300) NULL, trecho VARCHAR(300) NULL, recebido_em TIMESTAMP NULL,
  lido TINYINT(1) NOT NULL DEFAULT 0, categoria VARCHAR(40) NOT NULL DEFAULT 'Outros', categoria_manual TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  UNIQUE KEY email_mensagens_conta_id_uid_unique (conta_id, uid),
  KEY email_mensagens_user_id_recebido_em_index (user_id, recebido_em),
  CONSTRAINT email_mensagens_user_id_foreign FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT email_mensagens_conta_id_foreign FOREIGN KEY (conta_id) REFERENCES email_contas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE email_regras (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL, categoria VARCHAR(40) NOT NULL, campo VARCHAR(12) NOT NULL DEFAULT 'qualquer',
  termo VARCHAR(120) NOT NULL, ordem SMALLINT UNSIGNED NOT NULL DEFAULT 0, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  KEY email_regras_user_id_ordem_index (user_id, ordem),
  CONSTRAINT email_regras_user_id_foreign FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO migrations (migration, batch) SELECT '2026_11_06_000000_create_emails_tables', COALESCE(MAX(batch), 0) + 1 FROM migrations;
```

## Gmail pelo Google (opcional, deixado pronto)

1. console.cloud.google.com → projeto → ativar **Gmail API** → Tela de consentimento OAuth → credencial "Aplicativo da Web".
2. URI de redirecionamento autorizado: `https://SEU_DOMINIO/negocios/emails/google/retorno`.
3. No `.env`: `GOOGLE_CLIENT_ID=...`, `GOOGLE_CLIENT_SECRET=...` (e `GOOGLE_REDIRECT_URI` só se o endereço for outro).
4. `gmail.readonly` é escopo **restrito**: em modo "teste" só funciona para até 100 usuários de teste cadastrados; para
   abrir ao público o Google exige verificação do app e avaliação de segurança. Até lá, o Gmail por IMAP com senha de app funciona.
