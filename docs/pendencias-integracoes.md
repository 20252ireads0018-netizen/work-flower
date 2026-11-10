# Pendências das integrações

Montado em 10/11/2026 a partir das abas de integração e atualizado no mesmo dia, depois da rodada de configuração com o usuário. O repasse do OAuth usa o GitHub Pages deste repositório: `https://20252ireads0018-netizen.github.io/work-flower/`.

## Antes de tudo — FEITO
- [x] Merge das branches na `main` e push (as páginas de repasse em `docs/` estão no ar)
- [x] GitHub Pages servindo `main` `/docs`
- [x] `.env` do servidor: `INTEGRACOES_RELAY_URL=https://20252ireads0018-netizen.github.io/work-flower`
- [x] `.env` do servidor: `SESSION_LIFETIME=10080`

## Google (Drive, Gmail, YouTube) — FEITO
Projeto "My First Project" (ninth-sol-513721-a1), cliente OAuth "Work Flower site".
- [x] YouTube Data API v3 ativada
- [x] Escopos: `drive.file`, `gmail.readonly`, `youtube.readonly`
- [x] URI de retorno `.../google/youtube/` adicionada (junto das de Drive e Gmail)
- [x] Branding com página inicial, `/privacidade` e `/termos`; app "Em produção"
- [x] `.env` sem `GOOGLE_APP_PUBLICADO=false` / `GOOGLE_GMAIL_PUBLICO=false`
- [x] Migração `2026_11_10_000001_create_google_conexoes`
- [ ] Depois: pedir a verificação do Google (Central de verificação). Até lá aparece "app não verificado" e Gmail/YouTube ficam limitados a 100 contas.

## Lembretes por e-mail — FEITO
- [x] Caixa Titan `avisos@websight.com.br` (ativa); `MAIL_*` no `.env` (smtp.titan.email, 465, smtps). Teste de envio OK em 10/11/2026.
- [x] Cron do cPanel já existia: `schedule:run` a cada 15 min (lembretes podem chegar com até 15 min de atraso).
- Se a Titan voltar a recusar o login: conferir a senha da caixa e o acesso por outros aplicativos nas configurações do webmail.

## Instagram (Meta) — FEITO (modo de desenvolvimento)
- [x] App "Work Flower" ID 1867120691090079, caso de uso Instagram + Página, portfólio Joblinks Home office
- [x] Login do Facebook para Empresas, retorno `.../redes/instagram/`
- [x] Permissões `instagram_basic`, `instagram_manage_insights`, `pages_show_list`, `pages_read_engagement`, `business_management` (prontas para teste)
- [x] `.env`: `META_APP_ID`, `META_APP_SECRET`
- [ ] Para abrir a todos: verificar a empresa e mandar o app para análise da Meta
- [ ] Conectar a @workflower.app ao portfólio/anúncios: o botão abre pop-up, fazer pelo Chrome normal em https://business.facebook.com/latest/settings/instagram_account?business_id=423685814163747

## TikTok — CONFIGURADO, pronto para enviar à análise
- [x] App "Work Flower" ID 7706490767908587540 (Indivíduo), Login Kit, retorno `.../redes/tiktok/`
- [x] Escopos `user.info.basic`, `user.info.profile`, `user.info.stats`, `video.list`
- [x] URL prefix `https://20252ireads0018-netizen.github.io/work-flower/` verificado (arquivo `docs/tiktok*.txt`). O websight.com.br não serve: o mod_security da HostGator dá 406 para o robô do TikTok, e o DNS do domínio está numa conta da Cloudflare que não é a victorbarretophp@gmail.com.
- [x] Termos, privacidade e site apontando para as cópias em `docs/termos/`, `docs/privacidade/` e `docs/index.html`
- [x] `.env`: `TIKTOK_CLIENT_KEY`, `TIKTOK_CLIENT_SECRET`
- [x] Sandbox "Demo Work Flower" (7706514306800666645) com a conta jonas0.82 como testadora
- [x] Vídeo de demonstração real (`demo-tiktok-work-flower.mp4`, 1min34, 5,9 MB) no lugar do provisório; rascunho salvo
- [x] `.env` de volta com as chaves de PRODUÇÃO (a de Sandbox começa com "sb")
- [ ] O usuário clica em "Submit for review" e espera a análise do TikTok

## Shopee e Amazon — PULADAS (10/11/2026)
O usuário não vende na Shopee nem na Amazon. Os cartões continuam com a importação manual (CSV/números colados). Se um dia vender:
- Shopee: https://open.shopee.com, Redirect URL Domain `https://20252ireads0018-netizen.github.io`, `.env` `SHOPEE_PARTNER_ID`, `SHOPEE_PARTNER_KEY`.
- Amazon SP-API: https://sellercentral.amazon.com.br (Apps e serviços › Desenvolver apps), retorno `.../marketplaces/amazon/`, login `https://websight.com.br/work-flower/carteira/marketplaces`, `.env` `AMAZON_LWA_CLIENT_ID`, `AMAZON_LWA_CLIENT_SECRET`, `AMAZON_SP_APP_ID`.

As URIs de retorno precisam ser idênticas no app e no site, com a barra no fim. Passo a passo de Meta/TikTok/Shopee/Amazon em `docs/integracoes.md`.
