/* Work Flower · vagas em cartões (/vagas/cartoes). Componente Alpine "vagasCartoes".
   - Deslizar o cartão de cima: direita = candidatar (o portfólio /p/{slug} vai como currículo), esquerda = descartar.
     Funciona com toque/arraste (ponteiro), com os botões e com o teclado: ← descarta, → candidata, Z (ou Ctrl+Z) desfaz,
     ↑ abre/fecha os detalhes.
   - A resposta vai na hora para o servidor; "Desfazer" devolve o cartão ao topo e apaga a resposta.
   - Também publica vagas e mostra quem se candidatou às vagas da conta. */
(function () {
    'use strict';
    const LIMIAR = 110, LIMIAR_RAPIDO = 40, VEL = 0.55;

    function pedir(url, metodo, corpo) {
        return fetch(url, {
            method: metodo || 'POST',
            headers: {
                'Content-Type': 'application/json', Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            },
            body: corpo ? JSON.stringify(corpo) : null,
            credentials: 'same-origin',
        }).then(async r => {
            const j = await r.json().catch(() => ({}));
            if (!r.ok) {
                const e = new Error(j.erro || (j.errors ? Object.values(j.errors)[0][0] : (j.message || 'Não deu certo. Tente de novo.')));
                e.dados = j; e.status = r.status;
                throw e;
            }
            return j;
        });
    }

    window.vagasCartoes = function (cfg) {
        return {
            aba: 'deslizar',
            fila: cfg.fila || [],
            minhas: cfg.minhas || [],
            publicadas: cfg.publicadas || [],
            descartadas: cfg.descartadas || 0,
            portfolio: cfg.portfolio,
            statusNomes: cfg.status || {},
            hist: [],
            msg: '', comMsg: false,
            detalhes: false,
            aviso: '', avisoTipo: '',
            dx: 0, dy: 0, arrastando: false, saindo: null,
            ocupado: false,
            nova: { titulo: '', empresa: '', cidade: '', modalidade: 'presencial', regime: 'CLT', salario: '', descricao: '', requisitos: '', beneficios: '', link: '' },
            erros: {}, publicando: false, abertaPub: null,

            iniciar() {
                window.addEventListener('keydown', e => this.tecla(e));
            },
            get topo() { return this.fila[0] || null; },
            get podeCandidatar() { return !!(this.portfolio && this.portfolio.publicado); },
            avisar(t, tipo) {
                this.aviso = t; this.avisoTipo = tipo || '';
                clearTimeout(this._t);
                this._t = setTimeout(() => { this.aviso = ''; }, 4200);
            },

            /* ---------- arrastar ---------- */
            estiloCartao(i) {
                if (i === 0) {
                    const x = this.saindo ? (this.saindo === 'direita' ? 1 : -1) * (window.innerWidth + 200) : this.dx;
                    const rot = (this.saindo ? (this.saindo === 'direita' ? 1 : -1) * 30 : this.dx / 14);
                    const trans = this.arrastando ? 'none' : 'transform .32s cubic-bezier(.2,.8,.2,1)';
                    return `transform: translate(${x}px, ${this.saindo ? this.dy : this.dy * 0.25}px) rotate(${rot}deg); transition: ${trans}; z-index: 5`;
                }
                const s = 1 - i * 0.045, y = i * 12;
                return `transform: translateY(${y}px) scale(${s}); z-index: ${5 - i}; transition: transform .3s ease`;
            },
            intensidade(lado) {
                const v = lado === 'direita' ? this.dx : -this.dx;
                if (this.saindo) return this.saindo === lado ? 1 : 0;
                return Math.max(0, Math.min(1, v / LIMIAR));
            },
            pegar(e) {
                if (this.saindo || this.ocupado || !this.topo) return;
                if (e.button != null && e.button !== 0) return;
                if (e.target.closest('button, a, textarea, input, select')) return;
                const x0 = e.clientX, y0 = e.clientY, t0 = performance.now();
                const el = e.currentTarget;
                let ativo = false;
                const mover = ev => {
                    const dx = ev.clientX - x0, dy = ev.clientY - y0;
                    if (!ativo) {
                        if (Math.abs(dx) < 6 && Math.abs(dy) < 6) return;
                        if (Math.abs(dy) > Math.abs(dx) && ev.pointerType !== 'mouse') return fim(null); // rolar a página
                        ativo = true; this.arrastando = true;
                        try { el.setPointerCapture(ev.pointerId); } catch (_) {}
                    }
                    this.dx = dx; this.dy = dy;
                };
                const fim = ev => {
                    window.removeEventListener('pointermove', mover);
                    window.removeEventListener('pointerup', fim);
                    window.removeEventListener('pointercancel', fim);
                    if (!ativo) return;
                    this.arrastando = false;
                    const vel = Math.abs(this.dx) / Math.max(1, performance.now() - t0);
                    if (ev && ev.type === 'pointerup' && (Math.abs(this.dx) > LIMIAR || (Math.abs(this.dx) > LIMIAR_RAPIDO && vel > VEL))) {
                        this.decidir(this.dx > 0 ? 'candidatou' : 'descartou');
                    } else { this.dx = 0; this.dy = 0; }
                };
                window.addEventListener('pointermove', mover);
                window.addEventListener('pointerup', fim);
                window.addEventListener('pointercancel', fim);
            },
            tecla(e) {
                if (this.aba !== 'deslizar' || e.defaultPrevented) return;
                if (e.target.closest && e.target.closest('input, textarea, select, [contenteditable="true"]')) return;
                if (e.key === 'ArrowRight') { e.preventDefault(); this.decidir('candidatou'); }
                else if (e.key === 'ArrowLeft') { e.preventDefault(); this.decidir('descartou'); }
                else if (e.key === 'ArrowUp') { e.preventDefault(); this.detalhes = !this.detalhes; }
                else if ((e.key === 'z' || e.key === 'Z') && !e.altKey) { e.preventDefault(); this.desfazer(); }
            },

            /* ---------- decidir / desfazer ---------- */
            decidir(acao) {
                const v = this.topo;
                if (!v || this.saindo || this.ocupado) return;
                if (acao === 'candidatou' && !this.podeCandidatar) {
                    this.dx = 0; this.dy = 0;
                    this.avisar('Para se candidatar, publique seu portfólio: ele vai como seu currículo.', 'erro');
                    return;
                }
                this.saindo = acao === 'candidatou' ? 'direita' : 'esquerda';
                const mensagem = acao === 'candidatou' && this.comMsg ? this.msg.trim() : '';
                this.ocupado = true;
                setTimeout(() => {
                    this.fila.shift();
                    this.saindo = null; this.dx = 0; this.dy = 0; this.detalhes = false;
                    this.ocupado = false;
                }, 260);
                pedir(cfg.urls.responder.replace('__ID__', v.id), 'POST', { acao, mensagem })
                    .then(j => {
                        this.hist.push({ vaga: v, acao });
                        if (acao === 'candidatou') {
                            if (j.candidatura) this.minhas.unshift(j.candidatura);
                            if (mensagem) { this.msg = ''; this.comMsg = false; }
                            this.avisar(`Candidatura enviada para ${v.empresa}. Seu portfólio foi junto.`, 'ok');
                        } else {
                            this.descartadas++;
                        }
                    })
                    .catch(err => {
                        // volta o cartão para o topo
                        setTimeout(() => { if (!this.fila.some(x => x.id === v.id)) this.fila.unshift(v); }, 280);
                        if (err.dados && err.dados.portfolio === false) this.portfolio = this.portfolio ? { ...this.portfolio, publicado: false } : null;
                        this.avisar(err.message, 'erro');
                    });
            },
            desfazer() {
                const h = this.hist[this.hist.length - 1];
                if (!h || this.ocupado) return;
                this.ocupado = true;
                pedir(cfg.urls.desfazer.replace('__ID__', h.vaga.id), 'POST')
                    .then(() => {
                        this.hist.pop();
                        if (h.acao === 'candidatou') this.minhas = this.minhas.filter(m => m.vaga.id !== h.vaga.id);
                        else this.descartadas = Math.max(0, this.descartadas - 1);
                        this.fila = [h.vaga, ...this.fila.filter(x => x.id !== h.vaga.id)];
                        this.avisar('Desfeito. A vaga voltou para o topo.');
                    })
                    .catch(err => this.avisar(err.message, 'erro'))
                    .finally(() => { this.ocupado = false; });
            },
            retirar(m) {
                if (!confirm(`Retirar a candidatura para "${m.vaga.titulo}" (${m.vaga.empresa})?`)) return;
                pedir(cfg.urls.desfazer.replace('__ID__', m.vaga.id), 'POST')
                    .then(j => {
                        this.minhas = this.minhas.filter(x => x !== m);
                        this.hist = this.hist.filter(x => x.vaga.id !== m.vaga.id);
                        if (j.vaga) this.fila.push(j.vaga);
                        this.avisar('Candidatura retirada.');
                    })
                    .catch(err => this.avisar(err.message, 'erro'));
            },
            rever() {
                pedir(cfg.urls.rever, 'POST').then(j => {
                    this.fila = j.fila || []; this.descartadas = 0; this.hist = this.hist.filter(h => h.acao !== 'descartou');
                    this.avisar(this.fila.length ? 'As vagas descartadas voltaram para a pilha.' : 'Não há vagas abertas para rever.');
                }).catch(err => this.avisar(err.message, 'erro'));
            },

            /* ---------- publicar e acompanhar ---------- */
            publicar() {
                this.erros = {}; this.publicando = true;
                pedir(cfg.urls.publicar, 'POST', this.nova)
                    .then(j => {
                        this.publicadas.unshift(j.vaga);
                        this.nova = { titulo: '', empresa: this.nova.empresa, cidade: '', modalidade: 'presencial', regime: 'CLT', salario: '', descricao: '', requisitos: '', beneficios: '', link: '' };
                        this.abertaPub = j.vaga.id;
                        this.avisar('Vaga publicada. Ela já aparece na pilha de quem procura emprego.', 'ok');
                    })
                    .catch(err => {
                        if (err.dados && err.dados.errors) this.erros = Object.fromEntries(Object.entries(err.dados.errors).map(([k, v]) => [k, v[0]]));
                        this.avisar(err.message, 'erro');
                    })
                    .finally(() => { this.publicando = false; });
            },
            alternar(v) {
                pedir(cfg.urls.alternar.replace('__ID__', v.id), 'POST')
                    .then(j => { Object.assign(v, { ativa: j.vaga.ativa }); this.avisar(v.ativa ? 'Vaga reaberta.' : 'Vaga encerrada: saiu da pilha.'); })
                    .catch(err => this.avisar(err.message, 'erro'));
            },
            excluir(v) {
                if (!confirm(`Excluir a vaga "${v.titulo}" e as ${v.candidatos.length} candidaturas dela? Não dá para desfazer.`)) return;
                pedir(cfg.urls.excluir.replace('__ID__', v.id), 'DELETE')
                    .then(() => { this.publicadas = this.publicadas.filter(x => x !== v); this.avisar('Vaga excluída.'); })
                    .catch(err => this.avisar(err.message, 'erro'));
            },
            mudarStatus(c, status) {
                const antes = c.status;
                c.status = status;
                pedir(cfg.urls.status.replace('__ID__', c.id), 'POST', { status })
                    .catch(err => { c.status = antes; this.avisar(err.message, 'erro'); });
            },
            totalCandidatos() { return this.publicadas.reduce((s, v) => s + v.candidatos.length, 0); },
        };
    };
})();
