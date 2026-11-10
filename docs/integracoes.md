# Instagram, TikTok, Shopee e Amazon no Work Flower

As quatro integrações funcionam de dois jeitos:

1. **Importação manual** (já funciona, sem nada no `.env`): cada pessoa exporta a planilha da rede ou da loja
   (CSV) ou digita os números, e os dados aparecem como cartões, listas e gráficos do Work Flower.
2. **Conexão automática (OAuth)**: depois que você cria o app de desenvolvedor de cada rede e põe as chaves no
   `.env`, aparece o botão “Conectar” e os números passam a vir sozinhos.

| Integração | Onde aparece | O que mostra |
| --- | --- | --- |
| Instagram | Cartão **Criador de conteúdo** › aba Instagram | Seguidores, posts, alcance, visualizações, curtidas, engajamento, gráfico por post e de seguidores por dia |
| TikTok | Cartão **Criador de conteúdo** › aba TikTok (ao lado do YouTube) | Seguidores, curtidas, vídeos, visualizações, gráfico por vídeo e de seguidores por dia |
| Shopee | **Negócios › Marketplaces** › aba Shopee | Anúncios, vendas por dia, mais vendidos, tarifas e margem (somam no Resumo com os outros canais) |
| Amazon | **Negócios › Marketplaces** › aba Amazon | O mesmo da Shopee |

**Para o quadro:** no cartão Criador, arraste qualquer número, post ou o gráfico para um espaço vazio do quadro
(ou use o botão ⇱): vira um bloco de Texto, Tabela ou Gráfico. Na tela de Marketplaces, o botão ⇱ guarda o bloco
na Biblioteca (engrenagem › Biblioteca), para colar no quadro de qualquer área.

---

## 1. Páginas de repasse (GitHub Pages)

O firewall da HostGator (mod_security) às vezes recusa com erro 406 o retorno do login das redes. Por isso, igual
ao Google, o retorno passa por uma página estática no GitHub Pages que só repassa os parâmetros conhecidos.

As páginas já estão no repositório:

| Rede | Página | Repassa para |
| --- | --- | --- |
| Instagram | `docs/redes/instagram/index.html` | `https://websight.com.br/work-flower/redes/instagram/retorno` |
| TikTok | `docs/redes/tiktok/index.html` | `https://websight.com.br/work-flower/redes/tiktok/retorno` |
| Shopee | `docs/marketplaces/shopee/index.html` | `https://websight.com.br/work-flower/carteira/marketplaces/retorno/shopee` |
| Amazon | `docs/marketplaces/amazon/index.html` | `https://websight.com.br/work-flower/carteira/marketplaces/retorno/amazon` |

Passos:

1. No GitHub, o repositório já publica a pasta `docs/` no Pages (é o mesmo usado pelo `GOOGLE_RELAY_URL`).
   Se ainda não publica: **Settings › Pages › Branch: main / pasta /docs › Save**.
2. Depois do `git push`, confira que abre (troque `SEU-USUARIO` e o nome do repositório):
   `https://SEU-USUARIO.github.io/work-flower/redes/instagram/`
3. No `.env` do servidor, ponha a base (sem barra no fim):

   ```
   INTEGRACOES_RELAY_URL=https://SEU-USUARIO.github.io/work-flower
   ```

   Se ficar vazio, o site usa a base do `GOOGLE_RELAY_URL` (tirando o `/google` do fim). Se os dois estiverem
   vazios, o retorno vai direto para o site (use os endereços da coluna “Repassa para” no cadastro dos apps).
4. Se o domínio do site mudar, troque a linha `var DESTINO = ...` de cada página e publique de novo.

Os **endereços de retorno** que vão no cadastro de cada app são, então:

```
https://SEU-USUARIO.github.io/work-flower/redes/instagram/
https://SEU-USUARIO.github.io/work-flower/redes/tiktok/
https://SEU-USUARIO.github.io/work-flower/marketplaces/shopee/
https://SEU-USUARIO.github.io/work-flower/marketplaces/amazon/
```

(com a barra no fim; o endereço tem que ser idêntico no app e no `.env`).

---

## 2. Instagram (Meta · Instagram Graph API)

Requisito de cada pessoa que conecta: conta do Instagram **Profissional** (Comercial ou Criador de conteúdo)
ligada a uma **Página do Facebook**.

1. Entre em <https://developers.facebook.com/apps> com o Facebook do dono do site e clique em **Criar app**.
2. Caso de uso: **Outro** › tipo **Empresa**. Dê o nome “Work Flower” e crie.
3. No painel do app, adicione o produto **Login do Facebook para Empresas** (ou “Login do Facebook”).
4. Em **Login do Facebook › Configurações**, em “URIs de redirecionamento do OAuth válidos”, cole
   `https://SEU-USUARIO.github.io/work-flower/redes/instagram/` e salve.
5. Em **Configurações do app › Básico**: copie o **ID do app** e a **Chave secreta do app**; preencha o domínio
   do app (`websight.com.br`), a URL da Política de Privacidade e a categoria.
6. Em **Permissões e recursos**, peça: `instagram_basic`, `instagram_manage_insights`, `pages_show_list`,
   `pages_read_engagement`, `business_management`. Enquanto o app estiver em modo de desenvolvimento, só quem
   tem função no app (Funções do app › Testadores) consegue conectar; para todo mundo, faça a **Análise do app**
   da Meta pedindo essas permissões (eles pedem um vídeo mostrando o cartão Criador).
7. No `.env`:

   ```
   META_APP_ID=123456789012345
   META_APP_SECRET=abcdef0123456789abcdef0123456789
   # opcional: versão da Graph API
   META_GRAPH_VERSION=v21.0
   ```

O token dura cerca de 60 dias e o site renova sozinho quando faltam menos de 7.

**Sem o app:** no cartão Criador › Instagram › **Importar números**, cole o CSV do
**Meta Business Suite › Insights › Conteúdo › Exportar dados** (colunas como Horário de publicação, Descrição,
Tipo de publicação, Link permanente, Alcance, Visualizações, Curtidas, Comentários) e/ou digite seguidores, seguindo e posts.

---

## 3. TikTok (Login Kit + Display API)

1. Entre em <https://developers.tiktok.com>, faça login e vá em **Manage apps › Connect an app**.
2. Preencha nome, ícone, categoria, descrição, links de Termos e de Política de Privacidade.
   Plataforma: **Web**, com o site `https://websight.com.br/work-flower`.
3. Em **Products**, adicione **Login Kit** e **Display API**.
4. Em **Login Kit › Redirect URI**, cadastre `https://SEU-USUARIO.github.io/work-flower/redes/tiktok/`.
5. Em **Scopes**, marque: `user.info.basic`, `user.info.profile`, `user.info.stats`, `video.list`.
6. Envie para revisão (**Submit for review**). Até ser aprovado, só as contas de teste (Sandbox › Target users)
   conseguem conectar.
7. Copie **Client key** e **Client secret** para o `.env`:

   ```
   TIKTOK_CLIENT_KEY=aw1b2c3d4e5f6g7h
   TIKTOK_CLIENT_SECRET=abcdef0123456789abcdef0123456789
   ```

O token vale 24 horas e é renovado sozinho pelo refresh token (válido por 1 ano).

**Sem o app:** no cartão Criador › TikTok › **Importar números**, cole o CSV do **TikTok Studio › Análises ›
Baixar dados** (colunas como Video title, Post time, Total views, Total likes, Total comments, Total shares) e/ou
digite seguidores, curtidas e vídeos.

---

## 4. Shopee (Shopee Open Platform)

1. Cadastre-se como desenvolvedor em <https://open.shopee.com> (conta de vendedor Shopee Brasil) e aguarde a
   aprovação do cadastro.
2. Em **App Management › Create App**, escolha o tipo **ERP System / Seller In House System** (para uma loja
   própria) ou **Third-party Partner Platform** (para várias lojas de clientes).
3. Em **Redirect URL Domain**, cadastre o domínio do repasse: `https://SEU-USUARIO.github.io`
   (ou `https://websight.com.br` se não usar o repasse).
4. Depois da aprovação do app (“Go Live”), copie **Partner ID** e **Partner Key** (os de produção, não os de teste).
5. No `.env`:

   ```
   SHOPEE_PARTNER_ID=2001234
   SHOPEE_PARTNER_KEY=shpk1234567890abcdef...
   # teste (sandbox): o host informado no painel da Shopee, com o Partner ID/Key de teste
   SHOPEE_HOST=https://partner.shopeemobile.com
   ```

Permissões usadas: produtos (lista, preço, estoque, ativar/pausar) e pedidos (lista e detalhes dos últimos 30 dias).
O token da loja vale 4 horas e é renovado sozinho.

**Sem o app:** Negócios › Marketplaces › aba **Shopee** › **Importar planilha**. Use o arquivo de
**Meus Pedidos › Exportar** (pedidos) ou **Meus Produtos › Ferramentas em massa › Atualização em massa** (anúncios).

---

## 5. Amazon (Selling Partner API)

1. No **Seller Central** do Brasil (<https://sellercentral.amazon.com.br>), conta de vendedor Profissional,
   vá em **Apps e serviços › Desenvolver apps** e registre-se como desenvolvedor (perfil de desenvolvedor:
   “Aplicativo privado” para a sua própria loja, ou “público” para várias).
2. Clique em **Adicionar novo cliente de app**: nome “Work Flower”, tipo de API **SP API**, funções
   **Listagens de produtos** (Product Listing), **Precificação** (Pricing), **Inventário e pedidos** (Inventory and Order Tracking).
3. Em **OAuth Login URI** cadastre `https://websight.com.br/work-flower/carteira/marketplaces` e em
   **OAuth Redirect URI** cadastre `https://SEU-USUARIO.github.io/work-flower/marketplaces/amazon/`.
4. Em **Visualizar** as credenciais do LWA, copie o **ID do cliente** e o **Segredo do cliente**; copie também
   o **ID do aplicativo** (`amzn1.sp.solution....`).
5. No `.env`:

   ```
   AMAZON_LWA_CLIENT_ID=amzn1.application-oa2-client.xxxxxxxx
   AMAZON_LWA_CLIENT_SECRET=amzn1.oa2-cs.v1.xxxxxxxx
   AMAZON_SP_APP_ID=amzn1.sp.solution.xxxxxxxx
   # true enquanto o app não estiver publicado na Appstore (autorização em modo "beta")
   AMAZON_SP_RASCUNHO=true
   # já vêm certos para o Brasil:
   AMAZON_SELLER_CENTRAL=https://sellercentral.amazon.com.br
   AMAZON_SP_ENDPOINT=https://sellingpartnerapi-na.amazon.com
   AMAZON_MARKETPLACE_ID=A2Q3Y263D00KWC
   ```

**Sem o app:** Negócios › Marketplaces › aba **Amazon** › **Importar planilha**. Use **Relatórios › Relatórios de
pedidos** (pedidos; colunas `amazon-order-id`, `purchase-date`, `sku`, `quantity`, `item-price`, `order-status`) ou
**Inventário › Relatórios de inventário › Todas as ofertas** (anúncios; `seller-sku`, `item-name`, `price`, `quantity`).
O arquivo `.txt` separado por tabulação pode ser escolhido direto.

---

## 6. Depois de mexer no `.env`

Edite o `.env` pelo Gerenciador de Arquivos do cPanel. Se existir o arquivo `bootstrap/cache/config.php`
(configuração em cache), apague-o para o Laravel ler as chaves novas (com terminal: `php artisan config:clear`).
Os botões “Conectar” aparecem sozinhos quando as chaves estão preenchidas.

## 7. Banco de dados

A migração `2026_11_10_000001_integracoes_importacao_manual` cria a tabela `redes_manuais` (números importados do
Instagram/TikTok) e a coluna `mkt_contas.manual` (conta de marketplace alimentada por planilha, que não conta no
limite de contas do plano).
