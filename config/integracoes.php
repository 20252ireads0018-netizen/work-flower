<?php

/*
 * Integrações com Instagram, TikTok, Shopee e Amazon (passo a passo em docs/integracoes.md).
 *
 * "relay": endereço base das páginas de repasse publicadas no GitHub Pages (pasta docs/ do repositório).
 * O firewall da hospedagem (mod_security) pode barrar o retorno do OAuth com 406; a página de repasse
 * recebe o retorno, deixa passar só os parâmetros conhecidos e volta para o site. Vazio = retorno direto no site.
 * Se não for preenchido, usa a base do GOOGLE_RELAY_URL (sem o "/google" do fim).
 *   Ex.: INTEGRACOES_RELAY_URL=https://SEU-USUARIO.github.io/work-flower
 *   Endereços de retorno: {relay}/redes/instagram/  {relay}/redes/tiktok/
 *                         {relay}/marketplaces/shopee/  {relay}/marketplaces/amazon/
 */
return [
    'relay' => rtrim((string) (env('INTEGRACOES_RELAY_URL') ?: preg_replace('#/google/?$#', '', (string) env('GOOGLE_RELAY_URL', ''))), '/'),

    // importação manual (CSV ou colar números): limites por envio
    'max_linhas' => 2000,
    'max_bytes'  => 2 * 1024 * 1024,
];
