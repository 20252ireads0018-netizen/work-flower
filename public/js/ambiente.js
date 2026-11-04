/* Work Flower · Ambiente de cada workflow (partials/ambiente.blade.php).
   Cada aba (setor) pode ter fundo e música próprios; a aba sem os seus usa os da área, e a área sem fundo próprio
   usa o fundo geral da conta. As áreas avisam a aba aberta pelo evento "wf-setor" (window.wfSetorAtual guarda a última),
   e aqui o fundo e a música trocam na hora, sem recarregar. Se a música da aba nova for a mesma, ela continua tocando.
   Os navegadores só tocam som depois de uma interação: com "auto", a música começa no primeiro clique ou tecla.
   Quem pausar fica com a música pausada nesta área (localStorage "wf-amb-pausa.<área>"). */
(function () {
    'use strict';

    // mesmo valor de --papel no :root do layout (vale quando nem a aba, nem a área, nem a conta escolheram cor)
    const PAPEL = 'color-mix(in srgb, var(--prim) 2%, #090c10)';
    const CORES = ['#0e1319', '#111827', '#172033', '#1e1533', '#2a1520', '#13261f', '#262013', '#3a3f47'];
    const copia = o => JSON.parse(JSON.stringify(o));
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
    const guardar = (k, v) => { try { v === null ? localStorage.removeItem(k) : localStorage.setItem(k, v); } catch { /* sem armazenamento */ } };
    const ler = k => { try { return localStorage.getItem(k); } catch { return null; } };
    const PADRAO_SETOR = {
        fundo: { proprio: false, cor: null, ajuste: 'cover', escurecer: 55, desfoque: 0, imagem: false, versao: 0 },
        musica: { tipo: 'herdar', youtube: null, nome: null, volume: 40, auto: true, arquivo: false, versao: 0 },
    };

    /** Mesmas regras de App\Support\Ambiente::idYoutube. */
    function idYoutube(texto) {
        texto = String(texto || '').trim();
        if (/^[A-Za-z0-9_-]{11}$/.test(texto)) return texto;
        let u;
        try { u = new URL(texto); } catch { return null; }
        const host = u.hostname.toLowerCase();
        if (!/(^|\.)(youtube\.com|youtube-nocookie\.com|youtu\.be)$/.test(host)) return null;
        if (host === 'youtu.be') { const m = u.pathname.match(/^\/([A-Za-z0-9_-]{11})/); return m ? m[1] : null; }
        const v = u.searchParams.get('v');
        if (v && /^[A-Za-z0-9_-]{11}$/.test(v)) return v;
        const m = u.pathname.match(/^\/(?:shorts|embed|live|v)\/([A-Za-z0-9_-]{11})/);
        return m ? m[1] : null;
    }

    let ytApi = null;
    function carregarYoutube() {
        if (window.YT && window.YT.Player) return Promise.resolve();
        if (!ytApi) {
            ytApi = new Promise((ok, falha) => {
                const antes = window.onYouTubeIframeAPIReady;
                window.onYouTubeIframeAPIReady = () => { if (typeof antes === 'function') antes(); ok(); };
                const s = document.createElement('script');
                s.src = 'https://www.youtube.com/iframe_api';
                s.onerror = () => { ytApi = null; falha(new Error('youtube')); };
                document.head.appendChild(s);
            });
        }
        return ytApi;
    }

    function lerArquivo(arquivo) {
        return new Promise((ok, falha) => { const r = new FileReader(); r.onload = () => ok(r.result); r.onerror = falha; r.readAsDataURL(arquivo); });
    }
    async function reduzir(arquivo, lado, qualidade) {
        const bmp = await createImageBitmap(arquivo);
        const e = Math.min(1, lado / Math.max(bmp.width, bmp.height));
        const c = document.createElement('canvas');
        c.width = Math.round(bmp.width * e); c.height = Math.round(bmp.height * e);
        c.getContext('2d').drawImage(bmp, 0, 0, c.width, c.height);
        return c.toDataURL('image/jpeg', qualidade);
    }

    /** Põe um fundo ({ fu, url }) nas camadas #fundo-img / #fundo-veu do layout. */
    function aplicarFundo({ fu, url }) {
        const img = document.getElementById('fundo-img'), veu = document.getElementById('fundo-veu');
        document.documentElement.style.setProperty('--papel', fu.cor || PAPEL);
        if (!img || !veu) return;
        if (!url) { img.style.cssText = 'display:none;'; veu.style.cssText = 'display:none;'; return; }
        img.style.cssText = '';
        img.style.backgroundImage = 'url("' + url + '")';
        img.style.backgroundSize = fu.ajuste === 'repeat' ? 'auto' : fu.ajuste;
        img.style.backgroundRepeat = fu.ajuste === 'repeat' ? 'repeat' : 'no-repeat';
        img.style.filter = fu.desfoque ? 'blur(' + fu.desfoque + 'px)' : 'none';
        img.style.inset = fu.desfoque ? '-40px' : '0';
        veu.style.cssText = '';
        veu.style.background = 'color-mix(in srgb, var(--papel) ' + fu.escurecer + '%, transparent)';
    }

    window.wfAmbiente = function ({ amb, urls, area, edita, extra }) {
        extra = extra || {};
        return {
            PAPEL, CORES,
            amb, urls, area, edita,
            setores: extra.setores && !Array.isArray(extra.setores) ? extra.setores : {},
            geral: extra.geral || { cor: null, imagem: null, ajuste: 'cover', escurecer: 55, desfoque: 0 },
            de: extra.de || null,
            nomeArea: extra.nomeArea || area,
            setorAtual: window.wfSetorAtual || { id: '', nome: '' },

            // tocador
            atual: null, tocando: false, querSom: false, ytPronto: false, mostrarVideo: true, ytErro: '', ytTitulo: '',
            volume: 40, _yt: null, _ytId: null, _ytCriando: false, _querTocar: false,

            // painel
            aberto: false, salvando: false, enviando: false, baixando: false, erro: '', arrastando: null,
            escopo: 'area', fSetor: '', fSetorNome: '', f: null, novaImagem: null, removerImagem: false,

            get musica() { return this.amb.musica; },
            get temMusica() { return !!this.atual; },

            init() {
                this.f = this.formulario();
                this.aplicarTudo(true);
                const primeira = ev => {
                    // o clique no próprio tocador já decide sozinho
                    if (ev.target.closest && ev.target.closest('.wf-amb-pilula, .wf-amb-painel')) return;
                    ['pointerdown', 'keydown'].forEach(t => document.removeEventListener(t, primeira, true));
                    const m = this.musicaEfetiva().m;
                    if (m.auto && ler('wf-amb-pausa.' + this.area) !== '1') { this.querSom = true; if (this.atual && !this.tocando) this.tocar(); }
                };
                ['pointerdown', 'keydown'].forEach(t => document.addEventListener(t, primeira, true));
            },

            /* ---------- o que vale na aba aberta ---------- */
            cfgSetor(id) { return id && this.setores[id] ? this.setores[id] : null; },
            urlCom(base, setor, versao) {
                const p = new URLSearchParams();
                if (setor) p.set('setor', setor);
                if (versao) p.set('v', versao);
                if (this.de) p.set('de', this.de);
                const q = p.toString();
                return base + (q ? '?' + q : '');
            },
            fundoGeral() { return { fu: this.geral, url: this.geral.imagem || null }; },
            fundoDaArea() {
                const fu = this.amb.fundo;
                return fu.proprio ? { fu, url: fu.imagem ? this.urlCom(this.urls.baseImagem, null, fu.versao) : null } : this.fundoGeral();
            },
            fundoEfetivo() {
                const s = this.setorAtual.id, c = this.cfgSetor(s);
                if (c && c.fundo.proprio) return { fu: c.fundo, url: c.fundo.imagem ? this.urlCom(this.urls.baseImagem, s, c.fundo.versao) : null };
                return this.fundoDaArea();
            },
            musicaEfetiva() {
                const s = this.setorAtual.id, c = this.cfgSetor(s);
                return c && c.musica.tipo !== 'herdar' ? { m: c.musica, setor: s } : { m: this.amb.musica, setor: null };
            },
            fonteEfetiva() {
                const { m, setor } = this.musicaEfetiva();
                if (m.tipo === 'arquivo' && m.arquivo) {
                    const url = this.urlCom(this.urls.baseAudio, setor, m.versao);
                    return { tipo: 'arquivo', url, chave: 'a:' + url, nome: m.nome || 'Música', volume: m.volume, setor };
                }
                if (m.tipo === 'youtube' && m.youtube) return { tipo: 'youtube', id: m.youtube, chave: 'y:' + m.youtube, volume: m.volume, setor };
                return null;
            },
            aplicarTudo(inicio = false) {
                if (!inicio || this.setorAtual.id) aplicarFundo(this.fundoEfetivo());
                this.trocarMusica(this.fonteEfetiva());
            },
            mudouSetor(d) {
                this.setorAtual = { id: (d && d.id) || '', nome: (d && d.nome) || '' };
                if (!this.aberto) this.aplicarTudo();
            },
            nomeMusica() { return this.atual?.tipo === 'youtube' ? (this.ytTitulo || 'YouTube') : (this.atual?.nome || 'Música'); },
            ondeMusica() { return this.atual?.setor ? 'Música da aba ' + (this.setorAtual.nome || '') : 'Música de ' + this.nomeArea; },

            /* ---------- tocador ---------- */
            volumeDe(fonte) {
                const v = ler('wf-amb-vol.' + this.area + (fonte?.setor ? '.' + fonte.setor : ''));
                return v !== null ? Math.max(0, Math.min(100, +v)) : (fonte?.volume ?? 40);
            },
            trocarMusica(nova) {
                if ((nova?.chave || null) === (this.atual?.chave || null)) return;
                this.pararTudo();
                this.atual = nova;
                this.ytErro = ''; this.ytTitulo = '';
                if (!nova) return;
                this.volume = this.volumeDe(nova);
                if (nova.tipo === 'youtube') this.prepararYoutube(nova.id, this.querSom);
                else if (this.querSom) this.tocar();
            },
            async prepararYoutube(id, tocarJa) {
                this._ytId = id; this._querTocar = tocarJa;
                if (this._yt && this.ytPronto) {
                    tocarJa ? this._yt.loadVideoById(id) : this._yt.cueVideoById(id);
                    this._yt.setVolume(this.volume);
                    return;
                }
                if (this._ytCriando) return;
                this._ytCriando = true;
                try { await carregarYoutube(); } catch { this._ytCriando = false; this.ytErro = 'Não consegui carregar o YouTube.'; return; }
                this._yt = new window.YT.Player(this.$refs.yt, {
                    host: 'https://www.youtube-nocookie.com',
                    width: 220, height: 124, videoId: this._ytId,
                    playerVars: { controls: 1, playsinline: 1, modestbranding: 1, rel: 0 },
                    events: {
                        onReady: () => {
                            this.ytPronto = true;
                            this._yt.setVolume(this.volume);
                            if (this._ytId !== id) this._yt.cueVideoById(this._ytId);
                            if (this._querTocar && this.atual?.tipo === 'youtube') this._yt.playVideo();
                        },
                        onStateChange: e => {
                            if (e.data === 0) { this._yt.seekTo(0); this._yt.playVideo(); return; } // toca em laço
                            this.tocando = this.atual?.tipo === 'youtube' && (e.data === 1 || e.data === 3);
                            if (e.data === 1) { try { this.ytTitulo = this._yt.getVideoData().title || ''; } catch { /* sem título */ } }
                        },
                        // 101/150: o dono do vídeo não deixa tocar fora do YouTube; 100: vídeo removido ou privado
                        onError: e => {
                            this.tocando = false;
                            this.ytErro = [101, 150].includes(e.data) ? 'Este vídeo não deixa tocar fora do YouTube.' : 'Não consegui tocar este vídeo.';
                        },
                    },
                });
            },
            tocar() {
                this.querSom = true;
                guardar('wf-amb-pausa.' + this.area, null);
                const f = this.atual;
                if (!f) return;
                if (f.tipo === 'youtube') {
                    if (!this.ytPronto) { this._querTocar = true; return; }
                    if (this._ytId !== f.id) { this._ytId = f.id; this._yt.loadVideoById(f.id); } else this._yt.playVideo();
                    return;
                }
                const a = this.$refs.audio;
                if (a.dataset.src !== f.url) { a.src = f.url; a.dataset.src = f.url; }
                a.volume = this.volume / 100;
                a.play().then(() => { if (this.atual === f) this.tocando = true; }).catch(() => { this.tocando = false; });
            },
            pararTudo() {
                this.$refs.audio.pause();
                if (this._yt && this.ytPronto) { try { this._yt.pauseVideo(); } catch { /* ainda carregando */ } }
                this._querTocar = false;
                this.tocando = false;
            },
            pausar() {
                this.querSom = false;
                guardar('wf-amb-pausa.' + this.area, '1');
                this.pararTudo();
            },
            alternar() { this.tocando ? this.pausar() : this.tocar(); },
            mudarVolume(v) {
                this.volume = v;
                guardar('wf-amb-vol.' + this.area + (this.atual?.setor ? '.' + this.atual.setor : ''), String(v));
                if (this.atual?.tipo === 'youtube') { if (this.ytPronto) this._yt.setVolume(v); }
                else this.$refs.audio.volume = v / 100;
            },

            /* ---------- painel do dono ---------- */
            cfgForm() {
                if (this.escopo === 'setor') return this.cfgSetor(this.fSetor) || PADRAO_SETOR;
                return this.amb;
            },
            formulario() {
                const c = this.cfgForm();
                return {
                    modo: c.fundo.proprio ? (c.fundo.imagem ? 'imagem' : 'cor') : 'herdar',
                    fundo: copia(c.fundo),
                    musica: { ...copia(c.musica), youtube: c.musica.youtube ? 'https://youtu.be/' + c.musica.youtube : '' },
                };
            },
            abrir() {
                this.fSetor = this.setorAtual.id || '';
                this.fSetorNome = this.setorAtual.nome || '';
                // abre na aba aberta (dá para trocar para a área toda no topo do painel)
                this.escopo = this.fSetor ? 'setor' : 'area';
                this.recarregarForm();
                this.aberto = true;
            },
            recarregarForm() {
                this.f = this.formulario();
                this.novaImagem = null; this.removerImagem = false; this.erro = '';
                this.previa();
            },
            mudarEscopo(e) {
                if (this.escopo === e) return;
                this.escopo = e;
                this.recarregarForm();
            },
            cancelar() {
                this.aberto = false;
                this.aplicarTudo();
            },
            nomeSetorForm() { return this.fSetorNome || 'esta aba'; },
            dicaEscopo() {
                const proprios = Object.keys(this.setores).filter(k => { const c = this.setores[k]; return c.fundo.proprio || c.musica.tipo !== 'herdar'; }).length;
                if (this.escopo === 'setor') return 'Vale só quando a aba ' + this.nomeSetorForm() + ' estiver aberta. O que ficar como “Igual à área” segue a área ' + this.nomeArea + '.';
                const extra = proprios ? ' ' + (proprios === 1 ? '1 aba tem' : proprios + ' abas têm') + ' ambiente próprio e continua com ele.' : '';
                return 'Vale para todas as abas de ' + this.nomeArea + ' que não têm ambiente próprio.' + extra;
            },
            modoFundo(m) {
                this.f.modo = m;
                if (m !== 'herdar' && !this.f.fundo.cor) this.f.fundo.cor = CORES[0];
                this.previa();
            },
            urlImagemForm() {
                if (this.f.modo !== 'imagem') return null;
                if (this.novaImagem) return this.novaImagem;
                const c = this.cfgForm();
                if (!c.fundo.imagem || this.removerImagem) return null;
                return this.urlCom(this.urls.baseImagem, this.escopo === 'setor' ? this.fSetor : null, c.fundo.versao);
            },
            /** Fundo que o formulário está montando (o "herdar" mostra o que viria de cima). */
            previaFundo() {
                if (!this.f) return this.fundoGeral();
                if (this.f.modo === 'herdar') return this.escopo === 'setor' ? this.fundoDaArea() : this.fundoGeral();
                return { fu: this.f.fundo, url: this.urlImagemForm() };
            },
            previa() { aplicarFundo(this.previaFundo()); },
            estiloPreviaImg() {
                const { fu, url } = this.previaFundo();
                if (!url) return 'display:none';
                return 'background-image:url("' + url + '");background-size:' + (fu.ajuste === 'repeat' ? '40%' : fu.ajuste)
                    + ';background-repeat:' + (fu.ajuste === 'repeat' ? 'repeat' : 'no-repeat')
                    + (fu.desfoque ? ';filter:blur(' + Math.max(1, Math.round(fu.desfoque / 3)) + 'px);inset:-8px' : '');
            },
            estiloPreviaVeu() {
                const { fu, url } = this.previaFundo();
                return url ? 'background:color-mix(in srgb, ' + (fu.cor || PAPEL) + ' ' + fu.escurecer + '%, transparent)' : 'display:none';
            },
            resumoMusica(m) {
                if (!m) return 'sem música';
                if (m.tipo === 'arquivo') return m.arquivo ? (m.nome || 'áudio enviado') : 'sem música';
                if (m.tipo === 'youtube') return m.youtube ? 'vídeo do YouTube' : 'sem música';
                return 'sem música';
            },
            resumoMusicaForm() {
                const t = this.f?.musica.tipo;
                if (t === 'herdar') return 'Igual à área: ' + this.resumoMusica(this.amb.musica);
                if (t === 'nenhuma') return 'Silêncio';
                if (t === 'arquivo') return this.cfgForm().musica.arquivo ? (this.cfgForm().musica.nome || 'Áudio enviado') : 'Envie um áudio';
                if (t === 'youtube') return this.idYoutubeForm() ? 'YouTube' : 'Cole um link do YouTube';
                return '';
            },
            idYoutubeForm() { return idYoutube(this.f?.musica.youtube); },

            /** Link colado, imagem colada ou arrastada de outro site: baixa pelo servidor (public/js/baixar-imagem.js). */
            async usarDe(promessa) {
                this.erro = ''; this.baixando = true;
                try { const f = await promessa; if (f) await this.escolherImagem(f); else this.erro = 'Não achei uma imagem aí. Use o link direto do GIF.'; }
                catch (e) { this.erro = e.message || 'Não consegui baixar essa imagem.'; }
                finally { this.baixando = false; }
            },
            baixarLink(campo) { if (campo.value.trim() && window.wfBaixarImagem) this.usarDe(window.wfBaixarImagem(campo.value)).then(() => { if (!this.erro) campo.value = ''; }); },
            colarImagem(e) { const d = e.clipboardData; if (d && window.wfArquivoDe && [...d.items].some(i => i.kind === 'file')) { e.preventDefault(); this.usarDe(window.wfArquivoDe(d)); } },
            soltarImagem(dt) { if (window.wfArquivoDe) this.usarDe(window.wfArquivoDe(dt)); else this.escolherImagem(dt.files[0]); },
            async escolherImagem(arquivo) {
                if (!arquivo || !arquivo.type.startsWith('image/')) { if (arquivo) this.erro = 'Escolha um arquivo de imagem.'; return; }
                this.erro = '';
                try {
                    let url;
                    if (arquivo.type === 'image/gif' || arquivo.type === 'image/webp') {
                        if (arquivo.size > 4 * 1024 * 1024) { this.erro = 'Esse arquivo passa de 4 MB. Escolha um GIF ou WebP menor.'; return; }
                        url = await lerArquivo(arquivo);
                    } else {
                        url = await reduzir(arquivo, 1920, 0.8);
                        if (url.length > 2400000) url = await reduzir(arquivo, 1280, 0.7);
                    }
                    if (url.length > 5900000) { this.erro = 'O arquivo é muito pesado. Escolha outro.'; return; }
                    this.novaImagem = url;
                    this.removerImagem = false;
                    this.f.modo = 'imagem';
                    if (!this.f.fundo.cor) this.f.fundo.cor = CORES[0];
                    this.previa();
                } catch {
                    this.erro = 'Não consegui ler esse arquivo.';
                }
            },
            tirarImagem() { this.novaImagem = null; this.removerImagem = !!this.cfgForm().fundo.imagem; this.previa(); },

            urlEscopo(base) { return this.escopo === 'setor' && this.fSetor ? base + '?setor=' + encodeURIComponent(this.fSetor) : base; },
            guardarResposta(j) {
                if (j.setor) this.setores = { ...this.setores, [j.setor]: j.ambiente };
                else this.amb = j.ambiente;
            },
            async pedir(url, opcoes) {
                const r = await fetch(url, { credentials: 'same-origin', ...opcoes, headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf(), ...(opcoes.headers || {}) } });
                if (r.status === 401 || r.status === 419) { location.reload(); throw new Error('sessão'); }
                if (r.status === 413) throw new Error('O arquivo é grande demais para o servidor. Escolha um menor.');
                const j = await r.json().catch(() => ({}));
                if (r.status === 422) throw new Error(Object.values(j.errors || {})[0]?.[0] || 'Confira os dados e tente de novo.');
                if (!r.ok) throw new Error(j.message || 'Não deu certo. Tente de novo.');
                return j;
            },
            async enviarAudio(arquivo) {
                if (!arquivo) return;
                if (arquivo.size > 15 * 1024 * 1024) { this.erro = 'O áudio pode ter até 15 MB.'; return; }
                this.erro = ''; this.enviando = true;
                try {
                    const fd = new FormData();
                    fd.append('audio', arquivo);
                    this.guardarResposta(await this.pedir(this.urlEscopo(this.urls.enviar), { method: 'POST', body: fd }));
                    this.f.musica.tipo = 'arquivo';
                } catch (e) {
                    this.erro = e.message;
                } finally {
                    this.enviando = false;
                }
            },
            async removerAudio() {
                if (!confirm('Apagar este áudio?')) return;
                try {
                    this.guardarResposta(await this.pedir(this.urlEscopo(this.urls.remover), { method: 'DELETE' }));
                    if (this.f.musica.tipo === 'arquivo') this.f.musica.tipo = this.escopo === 'setor' ? 'herdar' : 'nenhuma';
                } catch (e) { this.erro = e.message; }
            },
            async salvar() {
                const m = this.f.musica;
                if (m.tipo === 'youtube' && !this.idYoutubeForm()) { this.erro = 'Cole um link de vídeo do YouTube.'; return; }
                this.erro = ''; this.salvando = true;
                try {
                    const c = this.cfgForm(), modo = this.f.modo;
                    const fundo = {
                        proprio: modo !== 'herdar',
                        cor: this.f.fundo.cor || null,
                        ajuste: this.f.fundo.ajuste, escurecer: +this.f.fundo.escurecer, desfoque: +this.f.fundo.desfoque,
                    };
                    if (modo === 'imagem' && this.novaImagem) fundo.imagem = this.novaImagem;
                    // a cor sozinha (ou a aba que voltou a seguir a área) não precisa mais da imagem guardada
                    else if (c.fundo.imagem && (this.removerImagem || modo === 'cor' || (modo === 'herdar' && this.escopo === 'setor'))) fundo.remover_imagem = true;
                    const j = await this.pedir(this.urlEscopo(this.urls.salvar), {
                        method: 'PUT',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ fundo, musica: { tipo: m.tipo, youtube: m.youtube || null, volume: +m.volume, auto: !!m.auto } }),
                    });
                    this.guardarResposta(j);
                    // o volume escolhido no painel passa a valer (esquece o ajuste rápido feito no tocador)
                    guardar('wf-amb-vol.' + this.area + (j.setor ? '.' + j.setor : ''), null);
                    this.aberto = false;
                    this.aplicarTudo();
                    // a mesma música continua tocando, só com o volume novo
                    const nova = this.fonteEfetiva();
                    if (this.atual && nova && nova.chave === this.atual.chave) {
                        this.atual = nova;
                        this.volume = this.volumeDe(nova);
                        if (nova.tipo === 'youtube') { if (this.ytPronto) this._yt.setVolume(this.volume); }
                        else this.$refs.audio.volume = this.volume / 100;
                    }
                } catch (e) {
                    this.erro = e.message;
                } finally {
                    this.salvando = false;
                }
            },
        };
    };
})();
