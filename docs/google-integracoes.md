# Google Drive, Gmail e YouTube: cartões do quadro e configuração no Google

Os três usam **o mesmo app OAuth do Google** (`GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET`, já no `.env` do servidor) e o
**repasse pelo GitHub Pages** (`GOOGLE_RELAY_URL`, pasta `docs/google/*`), porque o mod_security da HostGator recusa com 406
o retorno direto do Google (parâmetro `iss=https://accounts.google.com`).

## O que a pessoa vê

Cartões novos no menu **+ Bloco** de todas as áreas (`public/js/integracoes-cartoes.js`, `resources/views/partials/integracoes-cartoes.blade.php`):

| Cartão | Dados | O que dá para fazer |
|---|---|---|
| **Google Drive** | pasta "Work Flower" do Drive (escopo `drive.file`: o site só vê o que ele criou) | listar e buscar, renomear, abrir no Drive, **trazer para o quadro** (`.md/.txt` e Google Docs → texto, `.csv` e Planilhas → tabela, imagem → imagem, `.json` de bloco → o próprio cartão), **mandar um cartão da aba para o Drive**, backup na hora e backup automático diário |
| **Gmail** | caixas de Negócios › E-mails (Gmail pelo login do Google ou IMAP) | buscar, filtrar por categoria e caixa, **abrir a mensagem inteira** dentro do cartão, virar cartão de texto, virar **tarefa** da área, virar **evento** no calendário |
| **YouTube** | o canal da pessoa pelo login do Google (`youtube.readonly`), inclusive vídeos não listados, e playlists; ou um canal público pelo @ (com `YOUTUBE_API_KEY`) | assistir dentro do cartão, **cartão de um vídeo só** (player no quadro), **post no Criador de conteúdo** da aba |
| **Lembretes por e-mail** | eventos do calendário com lembrete | ver os próximos, ligar/desligar o e-mail, criar lembrete (inclusive a partir de um cartão da aba), virar cartão, teste de envio. Configuração do servidor: `docs/lembretes-por-email.md` |

Todo item de lista pode ser **arrastado para um espaço vazio do quadro** (vira cartão onde foi solto) ou mandado pelo botão "No quadro".
O cartão Drive conecta pelo mesmo fluxo de Configurações › Armazenamento (com `?de=cartao`, volta para a página do quadro).

## Saiu do "Em breve"

`App\Support\EmBreve` não lista mais Drive, Gmail e Lembretes. `GOOGLE_APP_PUBLICADO` e `GOOGLE_GMAIL_PUBLICO` agora valem
**true** por padrão. **Se o `.env` do servidor tiver `GOOGLE_APP_PUBLICADO=false` ou `GOOGLE_GMAIL_PUBLICO=false`, apague essas
linhas** (ou troque para `true`), senão só a administração consegue conectar.

## Configuração no Google Cloud (console.cloud.google.com, projeto do Work Flower)

1. **APIs e serviços › Biblioteca**: ative **Google Drive API**, **Gmail API** e **YouTube Data API v3**.
2. **Tela de permissão OAuth (Google Auth Platform) › Acesso a dados › Adicionar escopos**:
   - `.../auth/drive.file` (não sensível)
   - `.../auth/gmail.readonly` (restrito)
   - `.../auth/youtube.readonly` (sensível)
   - `openid` e `.../auth/userinfo.email`
3. **Clientes › (o cliente da Web) › URIs de redirecionamento autorizados**: os três endereços do repasse
   (o de YouTube é novo):
   ```
   https://USUARIO.github.io/work-flower/google/drive/
   https://USUARIO.github.io/work-flower/google/emails/
   https://USUARIO.github.io/work-flower/google/youtube/
   ```
   (o mesmo prefixo que está em `GOOGLE_RELAY_URL`, mais `/youtube/`). A página `docs/google/youtube/index.html` precisa
   estar publicada no GitHub Pages: ela sobe com o push da `main` para o GitHub.
4. **Público-alvo › Status de publicação: "Em produção"** (botão *Publicar app*). Enquanto estiver "Em teste", só os e-mails
   cadastrados em "Usuários de teste" conseguem entrar; as outras contas veem "Acesso bloqueado".
5. **Verificação**: com o app em produção,
   - **Drive** (`drive.file`) funciona para todos sem verificação.
   - **YouTube** (sensível) e **Gmail** (restrito) funcionam, mas o Google mostra a tela "O Google não verificou este app"
     (a pessoa clica em *Avançado › Acessar Work Flower*) e limita a **100 contas** até a verificação. Para tirar o aviso:
     *Central de verificação* › enviar para verificação (domínio verificado no Search Console, página inicial e política de
     privacidade públicas, vídeo mostrando o uso dos escopos). O Gmail restrito exige ainda a avaliação de segurança (CASA).
6. Opcional: `YOUTUBE_API_KEY` (APIs e serviços › Credenciais › Chave de API, restrita à YouTube Data API v3) deixa ver canais
   públicos pelo @ sem login e mantém as métricas da aba YouTube do cartão Criador.

## Migração nova

`database/migrations/2026_11_10_000001_create_google_conexoes.php` (tabela `google_conexoes`, tokens do YouTube criptografados).
SQL equivalente (MySQL), se a migração for rodada à mão no phpMyAdmin:

```sql
CREATE TABLE IF NOT EXISTS google_conexoes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  servico VARCHAR(20) NOT NULL,
  email VARCHAR(190) NULL,
  access_token TEXT NULL,
  refresh_token TEXT NULL,
  expira_em TIMESTAMP NULL,
  dados JSON NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  UNIQUE KEY google_conexoes_user_id_servico_unique (user_id, servico),
  CONSTRAINT google_conexoes_user_id_foreign FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO migrations (migration, batch) SELECT '2026_11_10_000001_create_google_conexoes', COALESCE(MAX(batch), 0) + 1 FROM migrations;
```

## Arquivos

```
app/Http/Controllers/GoogleDriveController.php   (novo)
app/Http/Controllers/LembretesController.php     (novo)
app/Http/Controllers/YoutubeController.php       (cartão + login do Google)
app/Http/Controllers/EmailController.php         (cartão, corpo da mensagem, volta para o quadro)
app/Http/Controllers/ArmazenamentoController.php (volta para o quadro depois de conectar o Drive)
app/Models/GoogleConexao.php                     (novo)
app/Support/Google/Youtube.php                   (novo)
app/Support/Emails/Corpo.php                     (novo) e Mime.php (texto com quebras de linha)
app/Support/Armazenamento/GoogleDrive.php        (listar, info, exportar, renomear)
app/Support/EmBreve.php, app/Support/MoverCartoes.php, config/services.php
routes/integracoes-cartoes.php (novo), routes/emails.php, routes/web.php (require)
public/js/integracoes-cartoes.js (novo), public/css/tailwind.css
resources/views/partials/integracoes-cartoes*.blade.php (novos), partials/graficos.blade.php,
resources/views/areas/{carteira,corpo,diverso,mente}/partials/blocos.blade.php, armazenamento/index.blade.php
docs/google/youtube/index.html (GitHub Pages)
```
