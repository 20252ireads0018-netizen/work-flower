@extends('layouts.app')

@section('titulo', 'Armazenamento')
@section('subtitulo', 'Veja quanto espaço sua conta usa e guarde cópias dos seus workflows fora do Work Flower: no seu bucket (AWS, Cloudflare R2, Backblaze B2) ou no seu Google Drive.')

@section('conteudo')
@php
    use App\Support\Armazenamento\Uso;
    $origens = ['manual' => 'Feito na hora', 'diario' => 'Backup diário', 'antes' => 'Cópia antes de restaurar'];
    $maior = max(1, max(array_column($uso['areas'], 'total')));
    $admin = auth()->user()?->eAdmin() ?? false;
@endphp
{{-- classes próprias: o Tailwind do site é pré-gerado e não tem estas --}}
<style>
    .arm-pagina { display: grid; gap: 1.5rem; align-items: start; }
    .arm-coluna { display: grid; grid-template-columns: minmax(0, 1fr); gap: 1.5rem; min-width: 0; }
    .arm-grade { display: grid; gap: .75rem; }
    .arm-larga { display: none; }
    .arm-pr { padding-right: .75rem; }
    .arm-ativo { background: var(--prim) !important; color: var(--on-prim) !important; }
    .arm-quebra { overflow-wrap: anywhere; }
    @media (min-width: 640px) {
        .arm-grade { grid-template-columns: 1fr 1fr; }
        .arm-inteira { grid-column: 1 / -1; }
        .arm-larga { display: table-cell; }
    }
    @media (min-width: 1100px) {
        .arm-pagina { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); }
    }
</style>

@if ($errors->any())
    <p class="erro p-3 rounded-lg mb-6 arm-quebra" role="alert" style="background: var(--linha)">{{ $errors->first() }}</p>
@endif

<div class="arm-pagina">
<div class="arm-coluna">
    {{-- ===================== uso ===================== --}}
    <section class="card p-5 space-y-4" aria-labelledby="arm-uso">
        <div class="flex flex-wrap items-end gap-x-4 gap-y-1">
            <h2 id="arm-uso" class="titulo text-xl flex-1">Quanto você usa</h2>
            <p class="text-sm texto-2">Plano {{ $uso['plano'] }} ·
                @if ($uso['limite']) até {{ Uso::legivel($uso['limite']) }} no site @else sem limite de espaço no site @endif
            </p>
        </div>
        <div>
            <p class="text-3xl font-semibold">{{ Uso::legivel($uso['total']) }} <span class="text-sm texto-2 font-normal">guardados no Work Flower</span></p>
            @if ($uso['fora'])
                <p class="text-sm texto-2">+ {{ Uso::legivel($uso['fora']) }} em imagens e PDFs que já estão no seu armazenamento</p>
            @endif
            @if ($uso['limite'])
                @php $pct = min(100, round($uso['total'] * 100 / $uso['limite'])); @endphp
                <div class="h-2 rounded-full mt-2" style="background: var(--linha)" role="progressbar" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100" aria-label="Espaço usado do plano">
                    <div class="h-2 rounded-full" style="width: {{ $pct }}%; background: {{ $pct > 90 ? '#c0392b' : 'var(--prim)' }}"></div>
                </div>
            @endif
        </div>
        <table class="w-full text-sm">
            <thead class="texto-2 text-left">
                <tr><th class="py-1 font-medium">Área</th><th class="font-medium arm-larga">Textos e quadros</th><th class="font-medium arm-larga">Imagens</th><th class="font-medium arm-larga">Arquivos</th><th class="font-medium text-right">Total</th></tr>
            </thead>
            <tbody>
                @foreach ($uso['areas'] as $slug => $a)
                    <tr class="border-t" style="border-color: var(--linha)">
                        <td class="py-2 arm-pr">
                            <span class="block">{{ $a['nome'] }}</span>
                            <span class="block h-1 rounded-full mt-1" style="width: {{ max(2, round($a['total'] * 100 / $maior)) }}%; background: var(--prim); opacity: .75" aria-hidden="true"></span>
                        </td>
                        <td class="arm-larga texto-2">{{ Uso::legivel($a['dados']) }}</td>
                        <td class="arm-larga texto-2">{{ Uso::legivel($a['imagens']) }}</td>
                        <td class="arm-larga texto-2">{{ $slug === 'mente' ? 'PDFs ' : '' }}{{ Uso::legivel($a['arquivos']) }}</td>
                        <td class="text-right font-medium">{{ Uso::legivel($a['total']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>

    {{-- ===================== arquivos grandes ===================== --}}
    <section class="card p-5 space-y-3" aria-labelledby="arm-arquivos">
        <h2 id="arm-arquivos" class="titulo text-xl">Imagens e PDFs grandes</h2>
        <p class="text-sm texto-2">Leve para o seu destino as imagens grandes dos quadros (acima de {{ Uso::legivel((int) config('armazenamento.imagem_grande')) }}) e os PDFs da Mente. O Work Flower guarda só o endereço e busca no seu armazenamento quando o quadro abre; quem você convidou para o workflow continua vendo.</p>
        <p class="text-sm"><b>{{ $pendentes }}</b> {{ $pendentes === 1 ? 'arquivo pode sair' : 'arquivos podem sair' }} do site · <b>{{ $movidos }}</b> já {{ $movidos === 1 ? 'está' : 'estão' }} no seu armazenamento</p>
        @if ($destinos->isEmpty())
            <p class="text-sm texto-2">Para usar, conecte primeiro um destino em <b>Seus destinos</b>.</p>
        @elseif (! $pendentes && ! $movidos)
            <p class="text-sm texto-2">Nada para levar agora: suas imagens e PDFs são pequenos ou ainda não existem.</p>
        @endif
        <div class="flex flex-wrap items-end gap-2">
            @if ($destinos->isNotEmpty() && $pendentes)
                <form method="POST" action="{{ route('armazenamento.mover') }}" class="flex flex-wrap items-end gap-2">
                    @csrf
                    <div class="min-w-0">
                        <label class="rotulo" for="arm-destino">Levar para</label>
                        <select id="arm-destino" name="destino_id" class="campo">
                            @foreach ($destinos as $d)<option value="{{ $d->id }}">{{ $d->nome }}</option>@endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn">Levar arquivos</button>
                </form>
            @endif
            @if ($movidos)
                <form method="POST" action="{{ route('armazenamento.trazer') }}" onsubmit="return confirm('Trazer as imagens e PDFs de volta para o site e apagar do seu armazenamento?')">
                    @csrf
                    <button type="submit" class="btn-sec">Trazer de volta para o site</button>
                </form>
            @endif
        </div>
        @if ($pendentes > \App\Support\Armazenamento\Arquivos::LOTE)
            <p class="text-xs texto-2">Os arquivos vão em grupos de até {{ \App\Support\Armazenamento\Arquivos::LOTE }} (ou 60 MB) por clique. Clique de novo até o contador zerar.</p>
        @endif
    </section>
</div>

<div class="arm-coluna">
    {{-- ===================== destinos ===================== --}}
    <section class="space-y-3" aria-labelledby="arm-destinos">
        <h2 id="arm-destinos" class="titulo text-xl">Seus destinos</h2>
        @forelse ($destinos as $d)
            <article class="card p-4 space-y-3">
                <div class="flex items-center gap-2 min-w-0">
                    <span class="tag shrink-0">{{ $d->tipo === 'gdrive' ? 'Google Drive' : 'Bucket S3' }}</span>
                    <b class="flex-1 min-w-0 truncate" title="{{ $d->nome }}">{{ $d->nome }}</b>
                </div>
                <p class="text-xs texto-2 arm-quebra" title="{{ $d->resumo }}">{{ $d->resumo }}</p>
                <p class="text-xs texto-2">
                    Último backup: {{ $d->ultimo_backup_em ? $d->ultimo_backup_em->format('d/m/Y H:i') : 'nenhum ainda' }}
                    · Backup diário {{ $d->backup_diario ? 'ligado (todo dia às 3h30)' : 'desligado' }}
                </p>
                @if ($d->erro)
                    <p class="erro text-sm arm-quebra">Último erro: {{ $d->erro }}</p>
                @endif
                <div class="flex flex-wrap items-center gap-2">
                    <form method="POST" action="{{ route('armazenamento.backup', $d) }}">
                        @csrf
                        <button class="btn !py-1" type="submit">Fazer backup agora</button>
                    </form>
                    <form method="POST" action="{{ route('armazenamento.atualizar', $d) }}">
                        @csrf @method('PATCH')
                        <input type="hidden" name="backup_diario" value="{{ $d->backup_diario ? 0 : 1 }}">
                        <button class="btn-sec !py-1" type="submit" aria-pressed="{{ $d->backup_diario ? 'true' : 'false' }}">
                            {{ $d->backup_diario ? 'Desligar backup diário' : 'Ligar backup diário' }}
                        </button>
                    </form>
                    <form method="POST" action="{{ route('armazenamento.testar', $d) }}">
                        @csrf
                        <button class="btn-sec !py-1" type="submit">Testar conexão</button>
                    </form>
                    <form method="POST" action="{{ route('armazenamento.remover', $d) }}" class="ml-auto"
                          onsubmit="return confirm(@js('Desconectar ' . $d->nome . '? Os backups que já estão lá continuam lá.'))">
                        @csrf @method('DELETE')
                        <button class="btn-sec !py-1" type="submit">Desconectar</button>
                    </form>
                </div>
            </article>
        @empty
            <div class="card p-4 text-sm texto-2">
                <p><b>Nenhum destino conectado.</b> Hoje seus dados ficam só no banco do Work Flower.</p>
                <p class="mt-1">Conecte um bucket ou o Google Drive aqui embaixo para fazer backups e tirar arquivos grandes do site.</p>
            </div>
        @endforelse

        <div class="card p-5 space-y-4" x-data="{ aba: '{{ $errors->hasAny(['s3', 'bucket', 'chave', 'segredo', 'endpoint', 'regiao', 'prefixo', 'nome']) ? 's3' : '' }}', servico: 'r2' }">
            <h3 class="titulo text-lg">Conectar um destino</h3>
            <div class="flex flex-wrap gap-2">
                <button type="button" class="btn-sec" :class="aba === 's3' && 'arm-ativo'" @click="aba = aba === 's3' ? '' : 's3'" :aria-expanded="aba === 's3'" aria-controls="arm-form-s3">
                    Bucket S3 (AWS, Cloudflare R2, Backblaze B2)
                </button>
                @if ($googleOk)
                    <a href="{{ route('armazenamento.google') }}" class="btn-sec">Google Drive</a>
                @else
                    <span class="btn-sec opacity-60 cursor-not-allowed" aria-disabled="true">Google Drive (indisponível)</span>
                @endif
            </div>
            @if (! $googleOk)
                @if ($admin)
                    <div class="text-xs texto-2 p-3 rounded-lg space-y-1" style="background: var(--linha)">
                        <p><b>Para liberar o Google Drive (só a administração vê isto):</b></p>
                        <ol class="list-decimal pl-5 space-y-1">
                            <li>Em console.cloud.google.com, crie um projeto e ative a "Google Drive API".</li>
                            <li>Configure a tela de consentimento OAuth com o escopo <code>.../auth/drive.file</code>.</li>
                            <li>Crie uma credencial "ID do cliente OAuth" (aplicativo da Web) com o URI de redirecionamento <code class="arm-quebra">{{ route('armazenamento.google.retorno') }}</code></li>
                            <li>Coloque no .env do servidor: <code>GOOGLE_CLIENT_ID=</code> e <code>GOOGLE_CLIENT_SECRET=</code></li>
                        </ol>
                    </div>
                @else
                    <p class="text-xs texto-2">O Google Drive não está disponível neste Work Flower agora. Enquanto isso, use um bucket.</p>
                @endif
            @endif

            <form id="arm-form-s3" method="POST" action="{{ route('armazenamento.s3') }}" class="arm-grade" x-show="aba === 's3'" x-cloak autocomplete="off">
                @csrf
                <div class="arm-inteira">
                    <label class="rotulo" for="arm-servico">Serviço</label>
                    <select id="arm-servico" class="campo" x-model="servico">
                        <option value="r2">Cloudflare R2</option>
                        <option value="b2">Backblaze B2</option>
                        <option value="aws">AWS S3</option>
                        <option value="outro">Outro compatível com S3</option>
                    </select>
                    <p class="text-xs texto-2 mt-1" x-show="servico === 'r2'">No painel da Cloudflare: R2 › crie o bucket › "Manage API tokens" com permissão de leitura e escrita. O endereço é https://&lt;id da conta&gt;.r2.cloudflarestorage.com.</p>
                    <p class="text-xs texto-2 mt-1" x-show="servico === 'b2'">No Backblaze: crie o bucket (privado) e uma "Application Key" com acesso a ele. O endereço aparece no bucket, ex.: https://s3.us-west-004.backblazeb2.com.</p>
                    <p class="text-xs texto-2 mt-1" x-show="servico === 'aws'">Na AWS: crie o bucket e um usuário IAM com s3:PutObject, s3:GetObject e s3:DeleteObject nele. Deixe o endereço vazio e informe a região.</p>
                    <p class="text-xs texto-2 mt-1" x-show="servico === 'outro'">Qualquer serviço com API S3 (Wasabi, MinIO público...). Informe o endereço https e a região.</p>
                </div>
                <div>
                    <label class="rotulo" for="arm-endpoint">Endereço (endpoint)</label>
                    <input id="arm-endpoint" name="endpoint" class="campo" value="{{ old('endpoint') }}" inputmode="url"
                           :placeholder="{ r2: 'https://<conta>.r2.cloudflarestorage.com', b2: 'https://s3.us-west-004.backblazeb2.com', aws: 'vazio = AWS', outro: 'https://...' }[servico]">
                </div>
                <div>
                    <label class="rotulo" for="arm-regiao">Região <span class="texto-2 font-normal">(opcional)</span></label>
                    <input id="arm-regiao" name="regiao" class="campo" value="{{ old('regiao') }}" :placeholder="servico === 'r2' ? 'auto' : 'us-east-1'">
                </div>
                <div>
                    <label class="rotulo" for="arm-bucket">Nome do bucket</label>
                    <input id="arm-bucket" name="bucket" class="campo" required value="{{ old('bucket') }}" placeholder="meus-backups">
                </div>
                <div>
                    <label class="rotulo" for="arm-prefixo">Pasta dentro do bucket</label>
                    <input id="arm-prefixo" name="prefixo" class="campo" value="{{ old('prefixo', 'work-flower') }}">
                </div>
                <div>
                    <label class="rotulo" for="arm-chave">Chave de acesso (Access Key ID)</label>
                    <input id="arm-chave" name="chave" class="campo" required spellcheck="false">
                </div>
                <div>
                    <label class="rotulo" for="arm-segredo">Chave secreta (Secret)</label>
                    <input id="arm-segredo" name="segredo" type="password" class="campo" required autocomplete="new-password">
                </div>
                <div class="arm-inteira">
                    <label class="rotulo" for="arm-nome">Nome para mostrar <span class="texto-2 font-normal">(opcional)</span></label>
                    <input id="arm-nome" name="nome" class="campo" maxlength="60" value="{{ old('nome') }}" placeholder="Ex.: Backups do escritório">
                </div>
                <p class="arm-inteira text-xs texto-2">As chaves ficam criptografadas no Work Flower e nunca voltam para o navegador. Ao conectar, gravamos e apagamos um arquivo de teste para conferir.</p>
                <div class="arm-inteira flex flex-wrap justify-end gap-2">
                    <button type="button" class="btn-sec" @click="aba = ''">Cancelar</button>
                    <button type="submit" class="btn">Testar e conectar</button>
                </div>
            </form>
        </div>
    </section>

    {{-- ===================== backups ===================== --}}
    <section class="card p-5 space-y-3" aria-labelledby="arm-backups">
        <h2 id="arm-backups" class="titulo text-xl">Backups</h2>
        <p class="text-sm texto-2">Cada backup leva as cinco áreas (Corpo, Mente, Carteira, Negócios e Diverso) no mesmo formato do <b>Importar / exportar</b>, então o arquivo baixado também pode ser importado à mão. Restaurar substitui as áreas marcadas; antes, uma cópia de como está agora vai para o mesmo destino.</p>
        @forelse ($backups as $b)
            <div class="border-t pt-3 flex flex-wrap items-center gap-2" style="border-color: var(--linha)" x-data="{ aberto: false }">
                <div class="flex-1 min-w-0" style="flex-basis: 14rem">
                    <p class="text-sm font-medium truncate" title="{{ $b->nome }} · {{ $b->destino?->nome }}">{{ $b->created_at->format('d/m/Y H:i') }} · {{ $b->destino?->nome }}</p>
                    <p class="text-xs texto-2">{{ $origens[$b->origem] ?? $b->origem }} · {{ Uso::legivel((int) $b->tamanho) }}</p>
                </div>
                <a class="btn-sec !py-1" href="{{ route('armazenamento.baixar', $b) }}">Baixar</a>
                <button type="button" class="btn-sec !py-1" @click="aberto = !aberto" :aria-expanded="aberto" x-show="!aberto">Restaurar…</button>
                <form method="POST" action="{{ route('armazenamento.restaurar', $b) }}" class="w-full space-y-2 p-3 rounded-lg" style="background: var(--prim-suave)"
                      x-show="aberto" x-cloak onsubmit="return confirm('Substituir as áreas marcadas pelo conteúdo deste backup?')">
                    @csrf
                    <p class="text-sm font-medium">Quais áreas voltar para este backup?</p>
                    <div class="flex flex-wrap gap-3">
                        @foreach ($areas as $slug => $nome)
                            @if (in_array($slug, explode(',', (string) $b->areas), true))
                                <label class="text-sm flex items-center gap-1"><input type="checkbox" name="areas[]" value="{{ $slug }}" checked> {{ $nome }}</label>
                            @endif
                        @endforeach
                    </div>
                    <div class="flex flex-wrap justify-end gap-2">
                        <button type="button" class="btn-sec !py-1" @click="aberto = false">Cancelar</button>
                        <button type="submit" class="btn !py-1">Restaurar marcadas</button>
                    </div>
                </form>
            </div>
        @empty
            <p class="text-sm texto-2">Nenhum backup ainda. {{ $destinos->isEmpty() ? 'Conecte um destino e depois clique em "Fazer backup agora".' : 'Clique em "Fazer backup agora" no seu destino ou ligue o backup diário.' }}</p>
        @endforelse
    </section>
</div>
</div>
@endsection
