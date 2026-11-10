# Pendências das integrações (resolver juntos, uma por vez)

Montado em 10/11/2026 a partir das abas de integração. O repasse do OAuth usa o GitHub Pages deste repositório: `https://20252ireads0018-netizen.github.io/work-flower/`. As páginas de repasse novas só existem depois do merge na `main` e do push.

## Antes de tudo
- [ ] Merge das branches na `main` e push (publica `docs/` no GitHub Pages)
- [ ] GitHub › Settings › Pages: branch `main`, pasta `/docs` — https://github.com/20252ireads0018-netizen/work-flower/settings/pages
- [ ] `.env` do servidor: `INTEGRACOES_RELAY_URL=https://20252ireads0018-netizen.github.io/work-flower`
- [ ] `.env` do servidor: `SESSION_LIFETIME=10080` (hoje está 120 = 2 h)

## Google (Drive, Gmail, YouTube) — docs/google-integracoes.md
- [ ] Google Cloud Console: https://console.cloud.google.com/apis/credentials
- [ ] Ativar YouTube Data API v3: https://console.cloud.google.com/apis/library/youtube.googleapis.com
- [ ] Escopo `youtube.readonly` na tela de consentimento: https://console.cloud.google.com/apis/credentials/consent
- [ ] URI de retorno: `https://20252ireads0018-netizen.github.io/work-flower/google/youtube/`
- [ ] Publicar o app ("Em produção"); Gmail/YouTube ficam limitados a 100 contas até a verificação do Google
- [ ] Apagar do `.env` `GOOGLE_APP_PUBLICADO=false` e `GOOGLE_GMAIL_PUBLICO=false`, se existirem
- [ ] Migração `2026_11_10_000001_create_google_conexoes`

## Lembretes por e-mail — docs/lembretes-por-email.md
- [ ] Criar conta de e-mail no cPanel: https://br910.hostgator.com.br:2083 (Contas de e-mail)
- [ ] Pôr `MAIL_*` (SMTP) no `.env`
- [ ] Cron do cPanel a cada minuto rodando `php artisan schedule:run`

## Instagram (Meta)
- [ ] Criar app: https://developers.facebook.com/apps
- [ ] Login do Facebook › Configurações, retorno: `https://20252ireads0018-netizen.github.io/work-flower/redes/instagram/`
- [ ] `.env`: `META_APP_ID`, `META_APP_SECRET`
- [ ] Conectar a @workflower.app ao Gerenciador de Anúncios: https://business.facebook.com/settings/instagram-account-v2s

## TikTok
- [ ] Criar app: https://developers.tiktok.com (Manage apps › Connect an app)
- [ ] Login Kit, Redirect URI: `https://20252ireads0018-netizen.github.io/work-flower/redes/tiktok/`
- [ ] `.env`: `TIKTOK_CLIENT_KEY`, `TIKTOK_CLIENT_SECRET`

## Shopee
- [ ] Cadastro e app: https://open.shopee.com
- [ ] Redirect URL Domain: `https://20252ireads0018-netizen.github.io`
- [ ] `.env`: `SHOPEE_PARTNER_ID`, `SHOPEE_PARTNER_KEY`

## Amazon (SP-API)
- [ ] Seller Central: https://sellercentral.amazon.com.br (Apps e serviços › Desenvolver apps)
- [ ] OAuth Redirect URI: `https://20252ireads0018-netizen.github.io/work-flower/marketplaces/amazon/`
- [ ] OAuth Login URI: `https://websight.com.br/work-flower/carteira/marketplaces`
- [ ] `.env`: `AMAZON_LWA_CLIENT_ID`, `AMAZON_LWA_CLIENT_SECRET`, `AMAZON_SP_APP_ID`

As URIs de retorno precisam ser idênticas no app e no site, com a barra no fim. Passo a passo de Meta/TikTok/Shopee/Amazon em `docs/integracoes.md`.
