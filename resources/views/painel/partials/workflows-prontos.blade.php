{{-- "Workflows prontos": galeria de temas no estilo mural (App\Support\WorkflowsProntos). Um clique cria a área
     com abas, cartões iniciais, fundo desenhado e a rádio do YouTube de cada aba, e abre a área nova. --}}
@php $wfTemas = \App\Support\WorkflowsProntos::galeria(); @endphp
<div x-data="{ aberto: false, criando: null }" @keydown.escape.window="aberto = false" class="contents">
    <button type="button" class="link-prim text-sm" @click="aberto = true">✨ Workflows prontos</button>

    @if (session('erro'))
        <div class="card p-3 text-sm basis-full" role="alert">{{ session('erro') }}</div>
    @endif

    <div x-show="aberto" x-cloak class="wf-prontos-fundo" role="dialog" aria-modal="true" aria-labelledby="wf-prontos-titulo" @click.self="aberto = false">
        <div class="wf-prontos-caixa">
            <div class="flex items-start gap-3">
                <div class="min-w-0 flex-1">
                    <h2 id="wf-prontos-titulo" class="titulo text-2xl">Workflows prontos</h2>
                    <p class="texto-2 text-sm mt-1">Escolha um tema: a área nasce com abas, cartões, fundo e música ambiente. Depois é só mudar o que quiser.</p>
                </div>
                <button type="button" class="wf-prontos-x" @click="aberto = false" aria-label="Fechar">&times;</button>
            </div>
            <div class="wf-prontos-mural">
                @foreach ($wfTemas as $i => $t)
                    <form method="POST" action="{{ route('workflows.prontos.criar', $t['id']) }}" class="wf-prontos-item"
                          @submit="criando = @js($t['id'])">
                        @csrf
                        <button type="submit" class="wf-prontos-cartao" :disabled="criando !== null"
                                aria-label="Criar o workflow {{ $t['nome'] }}">
                            <span class="wf-prontos-capa" style="background: linear-gradient(135deg, {{ $t['cores'][0] }}, {{ $t['cores'][1] }} 55%, {{ $t['cores'][2] }}); height: {{ [150, 190, 130, 170][$i % 4] }}px">
                                @if ($t['capa'])
                                    <img src="{{ $t['capa'] }}" alt="" loading="lazy">
                                @else
                                    <span class="wf-prontos-emoji" aria-hidden="true">{{ $t['emoji'] }}</span>
                                @endif
                            </span>
                            <span class="wf-prontos-info">
                                <span class="wf-prontos-identidade">{{ $t['identidade'] }}</span>
                                <b>{{ $t['nome'] }}</b>
                                <span class="texto-2 text-xs">{{ $t['frase'] }}</span>
                                <span class="wf-prontos-abas">
                                    @foreach ($t['abas'] as $aba)<span>{{ $aba }}</span>@endforeach
                                </span>
                                <span class="wf-prontos-criar" x-text="criando === @js($t['id']) ? 'Criando…' : 'Criar em um clique'">Criar em um clique</span>
                            </span>
                        </button>
                    </form>
                @endforeach
            </div>
            <p class="texto-2 text-xs mt-3">Cada workflow tem identidade visual própria: ilustração de fundo, cores dos cartões e música ambiente (rádios ao vivo do YouTube, que só tocam quando você aperta o botão de música da área).</p>
        </div>
    </div>
</div>
<style>
    .wf-prontos-fundo { position: fixed; inset: 0; z-index: 70; display: grid; place-items: center; padding: 1rem; background: rgba(10, 8, 20, .5); backdrop-filter: blur(3px); }
    .wf-prontos-caixa { width: min(60rem, 100%); max-height: calc(100vh - 2rem); overflow: auto; border-radius: 1.4rem; padding: 1.4rem;
        background: var(--papel); color: var(--tinta); border: 1px solid var(--linha); box-shadow: 0 24px 60px rgba(0,0,0,.35); }
    .wf-prontos-x { font-size: 1.6rem; line-height: 1; opacity: .7; background: none; border: 0; color: inherit; cursor: pointer; }
    .wf-prontos-mural { margin-top: 1rem; column-width: 13.5rem; column-gap: 1rem; }
    .wf-prontos-item { break-inside: avoid; margin-bottom: 1rem; }
    .wf-prontos-cartao { width: 100%; text-align: left; display: flex; flex-direction: column; border-radius: 1.1rem; overflow: hidden;
        border: 1px solid var(--linha); background: var(--papel); color: inherit; cursor: pointer; transition: transform .15s, box-shadow .15s; }
    .wf-prontos-cartao:hover:not(:disabled) { transform: translateY(-3px); box-shadow: 0 12px 28px rgba(0,0,0,.18); }
    .wf-prontos-cartao:disabled { opacity: .6; cursor: wait; }
    .wf-prontos-capa { display: grid; place-items: center; overflow: hidden; }
    .wf-prontos-capa img { width: 100%; height: 100%; object-fit: cover; transition: transform .3s; }
    .wf-prontos-cartao:hover .wf-prontos-capa img { transform: scale(1.04); }
    .wf-prontos-identidade { font-size: .66rem; font-weight: 700; letter-spacing: .07em; text-transform: uppercase; color: var(--tinta-2); }
    .wf-prontos-emoji { font-size: 2.6rem; filter: drop-shadow(0 4px 10px rgba(0,0,0,.25)); }
    .wf-prontos-info { display: flex; flex-direction: column; gap: .3rem; padding: .8rem .9rem .9rem; }
    .wf-prontos-abas { display: flex; flex-wrap: wrap; gap: .3rem; margin-top: .2rem; }
    .wf-prontos-abas span { font-size: .68rem; padding: .1rem .5rem; border-radius: 9999px; background: var(--prim-suave); }
    .wf-prontos-criar { margin-top: .4rem; font-size: .78rem; font-weight: 700; color: var(--prim-forte); }
</style>
