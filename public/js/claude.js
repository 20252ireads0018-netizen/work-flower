/* Work Flower · cartão "Claude" (conversa com o Claude que mexe nos cartões da página; quadro.js, seção "cartão Claude").
   Cada pessoa usa a PRÓPRIA chave da API da Anthropic: ela fica só neste navegador (localStorage) e as chamadas vão
   direto do navegador para a Anthropic, com o SDK oficial (@anthropic-ai/sdk, dangerouslyAllowBrowser). O custo cai
   na conta dona da chave; o servidor do Work Flower não vê a chave nem a conversa com a API.

   window.WFClaude.conversar({ chave, modelo, sistema, ferramentas, historico, executar, aoTexto, aoUso })
     historico  = mensagens da API (é alterado: entram as respostas e os resultados das ferramentas)
     executar   = async (bloco tool_use) => { conteudo, erro }   (quem pede permissão é o quadro)
     aoTexto    = texto do Claude para mostrar     aoUso = tokens gastos nesta chamada */
(function () {
    'use strict';
    if (window.WFClaude) return;

    const SDK_URL = 'https://esm.sh/@anthropic-ai/sdk@1.3.0';
    const MAX_VOLTAS = 10;      // chamadas seguidas numa mesma pergunta (cada ferramenta usada é uma volta)
    const CHAVE_LOCAL = 'wf-claude-chave';
    // modelos com recuperação no servidor quando o pedido é recusado por engano (o Haiku não tem)
    const COM_FALLBACK = ['claude-opus-5-5', 'claude-sonnet-5-5'];

    let sdk = null;
    const carregarSdk = () => (sdk ??= import(SDK_URL).catch(e => { sdk = null; throw e; }));

    const lerChave = () => { try { return localStorage.getItem(CHAVE_LOCAL) || ''; } catch { return ''; } };
    const gravarChave = v => { try { v ? localStorage.setItem(CHAVE_LOCAL, v) : localStorage.removeItem(CHAVE_LOCAL); } catch { /* sem armazenamento */ } };

    /** Mensagem legível para cada erro do SDK (classes tipadas, nada de comparar texto). */
    function explicar(A, e) {
        if (e instanceof A.AuthenticationError) return { msg: 'A chave da API não foi aceita. Confira se copiou a chave inteira.', chave: true };
        if (e instanceof A.PermissionDeniedError) return { msg: 'Esta chave não tem permissão para usar este modelo.' };
        if (e instanceof A.RateLimitError) return { msg: 'Muitos pedidos seguidos na sua conta da Anthropic. Espere um pouco e tente de novo.' };
        if (e instanceof A.BadRequestError) return { msg: 'A Anthropic recusou o pedido: ' + (e.error?.error?.message || e.message) };
        if (e instanceof A.APIConnectionError) return { msg: 'Sem conexão com a Anthropic. Verifique a internet.' };
        if (e instanceof A.APIError) return { msg: `Erro da Anthropic (${e.status ?? '?'}): ${e.error?.error?.message || e.message}` };
        return { msg: 'Não consegui falar com o Claude: ' + (e && e.message || e) };
    }

    async function conversar({ chave, modelo, sistema, ferramentas, historico, executar, aoTexto, aoUso, cancelado }) {
        let mod;
        try { mod = await carregarSdk(); } catch { throw { msg: 'Não consegui carregar a biblioteca do Claude. Verifique a internet.' }; }
        const A = mod.default;
        const cliente = new A({ apiKey: chave, dangerouslyAllowBrowser: true });

        for (let volta = 0; volta < MAX_VOLTAS; volta++) {
            if (cancelado && cancelado()) return { fim: 'cancelado' };
            const pedido = {
                model: modelo,
                max_tokens: 8000,
                // conversa: esforço baixo responde rápido e gasta menos; o pensamento fica a critério do modelo
                output_config: { effort: 'low' },
                cache_control: { type: 'ephemeral' },
                system: sistema,
                tools: ferramentas,
                messages: historico,
            };
            let resp;
            try {
                resp = COM_FALLBACK.includes(modelo)
                    ? await cliente.beta.messages.create({ ...pedido, betas: ['server-side-fallback-2026-07-01'], fallbacks: 'default' })
                    : await cliente.messages.create(pedido);
            } catch (e) { throw explicar(A, e); }

            // a resposta volta inteira para o histórico (blocos de pensamento inclusive, sem mexer)
            historico.push({ role: 'assistant', content: resp.content });
            if (aoUso && resp.usage) aoUso(resp.usage);
            const textos = resp.content.filter(b => b.type === 'text').map(b => b.text).join('\n').trim();
            if (textos) aoTexto(textos);

            if (resp.stop_reason === 'refusal') return { fim: 'recusa' };
            if (resp.stop_reason === 'max_tokens') return { fim: 'limite' };
            if (resp.stop_reason !== 'tool_use') return { fim: 'ok' };

            const usos = resp.content.filter(b => b.type === 'tool_use');
            const resultados = [];
            for (const u of usos) {
                let r;
                try { r = await executar(u); } catch (e) { r = { erro: String(e && e.message || e) }; }
                resultados.push(r.erro
                    ? { type: 'tool_result', tool_use_id: u.id, is_error: true, content: r.erro }
                    : { type: 'tool_result', tool_use_id: u.id, content: typeof r.conteudo === 'string' ? r.conteudo : JSON.stringify(r.conteudo) });
            }
            // todos os resultados numa mensagem só
            historico.push({ role: 'user', content: resultados });
        }
        return { fim: 'voltas' };
    }

    window.WFClaude = { conversar, lerChave, gravarChave };
})();
