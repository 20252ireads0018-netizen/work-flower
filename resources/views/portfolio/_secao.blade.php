{{-- Uma seção do portfólio. Variáveis da página: $s (seção), $p (perfil), $img, $externo, $previa, $pf. --}}
@php
    use App\Models\Portfolio;
    $ancora = $s['tipo'] === 'contato' ? 'contato' : 's-' . $s['id'];
    $cols = $s['colunas'];
    $tags = fn ($t) => array_filter(array_map('trim', explode(',', $t)));
@endphp
<section id="{{ $ancora }}" class="pf-secao pf-tipo-{{ $s['tipo'] }} pf-fundo-{{ $s['fundo'] }}">
    <div class="pf-max">
        <div class="pf-in">
            @if ($s['titulo'] !== '' || $s['subtitulo'] !== '')
                <div class="pf-cab pf-rv">
                    @if ($s['titulo'] !== '') <h2 class="pf-st">{{ $s['titulo'] }}</h2> @endif
                    @if ($s['subtitulo'] !== '') <p class="pf-sub">{{ $s['subtitulo'] }}</p> @endif
                </div>
            @endif
            <div class="pf-corpo">
            @switch($s['tipo'])

                @case('sobre')
                @case('texto')
                    @if ($s['estilo'] === 'dividido' && $s['img'])
                        <div class="pf-div pf-rv"><div class="pf-pre">{{ $s['texto'] }}</div><img src="{{ $img($s['img']) }}" alt="" loading="lazy"></div>
                    @elseif ($s['estilo'] === 'cartao')
                        <div class="pf-cartao pf-item pf-pre pf-rv">{{ $s['texto'] }}</div>
                    @elseif ($s['estilo'] === 'destaque')
                        <p class="pf-texto-destaque pf-pre pf-rv">{{ $s['texto'] }}</p>
                    @else
                        @if ($s['img']) <img src="{{ $img($s['img']) }}" alt="" loading="lazy" class="pf-rv" style="border-radius:var(--raio);margin-bottom:20px;max-height:420px;width:100%;object-fit:cover"> @endif
                        <p class="pf-pre pf-rv" style="max-width:70ch">{{ $s['texto'] }}</p>
                    @endif
                    @break

                @case('projetos')
                    @if ($s['texto'] !== '') <p class="pf-pre pf-suave pf-rv" style="margin-bottom:20px">{{ $s['texto'] }}</p> @endif
                    @if ($s['estilo'] === 'destaque')
                        <div class="pf-destaque">
                            @foreach ($s['itens'] as $i)
                                <article class="pf-rv">
                                    @if ($i['img']) <img src="{{ $img($i['img']) }}" alt="{{ $i['titulo'] }}" loading="lazy"> @else <div></div> @endif
                                    <div style="display:grid;gap:10px">
                                        @if ($i['subtitulo']) <span class="pf-per">{{ $i['subtitulo'] }}</span> @endif
                                        <h3 style="font-size:1.6rem">{{ $i['titulo'] }}</h3>
                                        @if ($i['texto']) <p class="pf-pre pf-suave">{{ $i['texto'] }}</p> @endif
                                        @if ($tags($i['tags'])) <div class="pf-tags">@foreach ($tags($i['tags']) as $tg) <span class="pf-chip">{{ $tg }}</span> @endforeach</div> @endif
                                        @if ($i['link']) <a class="pf-btn" style="justify-self:start" href="{{ $i['link'] }}" @if ($externo($i['link'])) target="_blank" rel="noopener" @endif>Ver projeto ↗</a> @endif
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    @else
                        <div class="{{ $s['estilo'] === 'lista' ? 'pf-lista' : ($s['estilo'] === 'mosaico' ? 'pf-mosaico' : 'pf-grade') }}" style="--cols: {{ $cols }}">
                            @foreach ($s['itens'] as $i)
                                <article class="pf-cartao pf-proj pf-rv {{ $s['estilo'] === 'lista' ? 'pf-linha-proj' : '' }}">
                                    @if ($i['img']) <img src="{{ $img($i['img']) }}" alt="{{ $i['titulo'] }}" loading="lazy"> @endif
                                    <div class="pf-item">
                                        @if ($i['subtitulo']) <span class="pf-per">{{ $i['subtitulo'] }}</span> @endif
                                        <h3>{{ $i['titulo'] }}</h3>
                                        @if ($i['texto']) <p class="pf-pre pf-suave">{{ $i['texto'] }}</p> @endif
                                        @if ($tags($i['tags'])) <div class="pf-tags">@foreach ($tags($i['tags']) as $tg) <span class="pf-chip">{{ $tg }}</span> @endforeach</div> @endif
                                        @if ($i['link']) <a class="pf-ver" href="{{ $i['link'] }}" @if ($externo($i['link'])) target="_blank" rel="noopener" @endif>Ver projeto ↗</a> @endif
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    @endif
                    @break

                @case('experiencia')
                @case('formacao')
                    @if ($s['texto'] !== '') <p class="pf-pre pf-suave pf-rv" style="margin-bottom:20px">{{ $s['texto'] }}</p> @endif
                    <div class="{{ $s['estilo'] === 'linha' ? 'pf-tempo' : ($s['estilo'] === 'cartoes' ? 'pf-grade' : 'pf-lista') }}" style="--cols: {{ min($cols, 2) }}">
                        @foreach ($s['itens'] as $i)
                            <div class="pf-rv {{ $s['estilo'] === 'cartoes' ? 'pf-cartao pf-item' : '' }}">
                                @if ($i['periodo']) <p class="pf-per">{{ $i['periodo'] }}</p> @endif
                                <h3 style="font-size:1.15rem">{{ $i['titulo'] }}</h3>
                                @if ($i['subtitulo']) <p class="pf-suave">{{ $i['subtitulo'] }}</p> @endif
                                @if ($i['texto']) <p class="pf-pre" style="margin-top:8px">{{ $i['texto'] }}</p> @endif
                            </div>
                        @endforeach
                    </div>
                    @break

                @case('habilidades')
                    @if ($s['texto'] !== '') <p class="pf-pre pf-suave pf-rv" style="margin-bottom:20px">{{ $s['texto'] }}</p> @endif
                    @if ($s['estilo'] === 'etiquetas')
                        <div class="pf-chips pf-rv">@foreach ($s['itens'] as $i) <span class="pf-chip">{{ $i['titulo'] }}</span> @endforeach</div>
                    @elseif ($s['estilo'] === 'circulos')
                        <div class="pf-circ">@foreach ($s['itens'] as $i) <div class="pf-rv"><div class="pf-anel" style="--n: {{ $i['nivel'] }}">{{ $i['nivel'] }}%</div>{{ $i['titulo'] }}</div> @endforeach</div>
                    @elseif ($s['estilo'] === 'grade')
                        <div class="pf-grade" style="--cols: {{ max(2, $cols) }}">@foreach ($s['itens'] as $i) <div class="pf-cartao pf-item pf-rv" style="padding:16px"><b>{{ $i['titulo'] }}</b><div class="pf-barra"><i style="width: {{ $i['nivel'] }}%"></i></div></div> @endforeach</div>
                    @else
                        <div class="pf-hab-barras">
                            @foreach ($s['itens'] as $i)
                                <div class="pf-rv"><div style="display:flex;justify-content:space-between"><span>{{ $i['titulo'] }}</span><span class="pf-suave">{{ $i['nivel'] }}%</span></div><div class="pf-barra"><i style="width: {{ $i['nivel'] }}%"></i></div></div>
                            @endforeach
                        </div>
                    @endif
                    @break

                @case('servicos')
                    @if ($s['texto'] !== '') <p class="pf-pre pf-suave pf-rv" style="margin-bottom:20px">{{ $s['texto'] }}</p> @endif
                    <div class="{{ $s['estilo'] === 'lista' ? 'pf-lista' : 'pf-grade' }}" style="--cols: {{ $cols }}">
                        @foreach ($s['itens'] as $i)
                            <div class="pf-cartao pf-item pf-rv" style="display:flex;flex-direction:column;gap:8px">
                                <h3>{{ $i['titulo'] }}</h3>
                                @if ($i['texto']) <p class="pf-pre pf-suave">{{ $i['texto'] }}</p> @endif
                                @if ($i['subtitulo']) <p style="margin-top:auto;font-weight:800;color:var(--cor)">{{ $i['subtitulo'] }}</p> @endif
                            </div>
                        @endforeach
                    </div>
                    @break

                @case('numeros')
                    <div class="pf-grade" style="--cols: {{ max(2, min(4, count($s['itens']))) }}">
                        @foreach ($s['itens'] as $i)
                            <div class="pf-num pf-rv {{ $s['estilo'] === 'cartoes' ? 'pf-cartao pf-item' : '' }}"><b>{{ $i['titulo'] }}</b><span class="pf-suave">{{ $i['subtitulo'] }}</span></div>
                        @endforeach
                    </div>
                    @break

                @case('depoimentos')
                    <div class="{{ $s['estilo'] === 'citacao' ? 'pf-citacao' : 'pf-grade' }}" style="--cols: {{ min($cols, 3) }}">
                        @foreach ($s['itens'] as $i)
                            <figure class="pf-dep pf-rv {{ $s['estilo'] === 'citacao' ? '' : 'pf-cartao pf-item' }}" style="margin:0">
                                <blockquote class="pf-pre">{{ $i['texto'] }}</blockquote>
                                @if ($i['titulo'] || $i['img'])
                                    <figcaption class="pf-autor">
                                        @if ($i['img']) <img src="{{ $img($i['img']) }}" alt="" loading="lazy"> @endif
                                        <span><b>{{ $i['titulo'] }}</b>@if ($i['subtitulo']) <br><span class="pf-suave" style="font-size:.85rem">{{ $i['subtitulo'] }}</span> @endif</span>
                                    </figcaption>
                                @endif
                            </figure>
                        @endforeach
                    </div>
                    @break

                @case('galeria')
                    <div class="pf-gal {{ $s['estilo'] === 'mosaico' ? 'pf-mosaico' : ($s['estilo'] === 'carrossel' ? 'pf-carrossel' : 'pf-grade') }}" style="--cols: {{ $cols }}">
                        @foreach ($s['itens'] as $i)
                            @if ($i['img'])
                                <figure class="pf-rv">
                                    <a href="{{ $i['link'] ?: $img($i['img']) }}" target="_blank" rel="noopener"><img src="{{ $img($i['img']) }}" alt="{{ $i['titulo'] }}" loading="lazy"></a>
                                    @if ($i['titulo']) <figcaption>{{ $i['titulo'] }}</figcaption> @endif
                                </figure>
                            @endif
                        @endforeach
                    </div>
                    @break

                @case('links')
                    <div class="pf-links {{ $s['estilo'] }}">
                        @foreach ($s['itens'] as $i)
                            @if ($i['link']) <a class="pf-rv" href="{{ $i['link'] }}" @if ($externo($i['link'])) target="_blank" rel="noopener" @endif>{{ $i['titulo'] ?: $i['link'] }}</a> @endif
                        @endforeach
                    </div>
                    @break

                @case('contato')
                    @php $abrirCartao = $s['estilo'] === 'cartao'; @endphp
                    <div class="{{ $s['estilo'] === 'dividido' ? 'pf-div' : '' }} {{ $abrirCartao ? 'pf-cartao pf-item' : '' }} pf-rv" style="{{ $s['estilo'] === 'dividido' ? 'align-items:start' : 'max-width:680px' }}">
                        <div style="display:grid;gap:14px">
                            @if ($s['texto'] !== '') <p class="pf-pre">{{ $s['texto'] }}</p> @endif
                            @if ($p['email'] || $p['telefone'])
                                <p style="display:flex;flex-wrap:wrap;gap:16px">
                                    @if ($p['email']) <a href="mailto:{{ $p['email'] }}">✉️ {{ $p['email'] }}</a> @endif
                                    @if ($p['telefone']) <a href="https://wa.me/{{ strlen(preg_replace('/\D/', '', $p['telefone'])) <= 11 ? '55' : '' }}{{ preg_replace('/\D/', '', $p['telefone']) }}" target="_blank" rel="noopener">📱 {{ $p['telefone'] }}</a> @endif
                                </p>
                            @endif
                            @if ($s['img'] && $s['estilo'] === 'dividido') <img src="{{ $img($s['img']) }}" alt="" loading="lazy" style="border-radius:var(--raio)"> @endif
                        </div>
                        @if ($s['formulario'])
                            <div>
                                @if (! $previa && session('pf_ok')) <div class="pf-aviso" role="status">{{ session('pf_ok') }}</div> @endif
                                @if (! $previa && $errors->any()) <div class="pf-aviso erro" role="alert">{{ $errors->first() }}</div> @endif
                                <form class="pf-form" method="post" action="{{ $previa || ! $pf->exists ? '#' : route('portfolio.contato', $pf->slug) }}" @if ($previa) onsubmit="return false" @endif>
                                    @unless ($previa) @csrf @endunless
                                    <div class="pf-oculto" aria-hidden="true"><label>Site <input name="site" tabindex="-1" autocomplete="off"></label></div>
                                    <div class="pf-grade" style="--cols:2;gap:12px">
                                        <label>Seu nome <input class="pf-campo" name="nome" required maxlength="120" value="{{ $previa ? '' : old('nome') }}" autocomplete="name"></label>
                                        <label>E-mail ou WhatsApp <input class="pf-campo" name="contato" required maxlength="160" value="{{ $previa ? '' : old('contato') }}" autocomplete="email"></label>
                                    </div>
                                    <label>Assunto <input class="pf-campo" name="assunto" maxlength="160" value="{{ $previa ? '' : old('assunto') }}"></label>
                                    <label>Mensagem <textarea class="pf-campo" name="mensagem" rows="5" required minlength="5" maxlength="3000">{{ $previa ? '' : old('mensagem') }}</textarea></label>
                                    <div><button class="pf-btn" type="submit" @if ($previa) disabled title="Na prévia o formulário não envia" @endif>Enviar mensagem</button></div>
                                </form>
                            </div>
                        @endif
                    </div>
                    @break
            @endswitch
            </div>
        </div>
    </div>
</section>
