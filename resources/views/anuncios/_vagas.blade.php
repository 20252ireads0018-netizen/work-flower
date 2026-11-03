{{-- Cena final de escassez, igual nos vídeos de anúncio: só os 30 primeiros ganham Premium grátis por 3 meses.
     Recebe: $de, $ate (tempo da cena), $prim, $onPrim e $chamada (frase de cima). As 30 vagas vão acendendo uma a uma. --}}
<section class="cena" data-de="{{ $de }}" data-ate="{{ $ate }}" style="background: radial-gradient(80% 50% at 50% 45%, rgba(255,120,40,.25), transparent 70%)">
    <span class="tag" data-a="sobe" style="color:#ffb15e;border-color:#ffb15e">🔥 OFERTA DE LANÇAMENTO</span>
    <p class="t m" data-a="sobe" data-em=".1">{{ $chamada }}</p>
    <p class="t x ouro" data-a="zoom sacode" data-em=".35" data-dur=".25" style="font-size:170px;white-space:nowrap">Só 30 vagas</p>
    <p class="t p" data-a="sobe" data-em=".6" style="margin-top:14px">Os <mark>30 primeiros</mark> ganham</p>
    <p class="t g verde" data-a="pop" data-em=".8" data-dur=".3" style="margin-top:10px">Premium grátis</p>
    <p class="t m" data-a="sobe" data-em=".95">por 3 meses.</p>
    <div data-a="sobe" data-em="1.1" style="display:grid;grid-template-columns:repeat(10,1fr);gap:12px;width:100%;margin-top:44px">
        @foreach (range(0, 29) as $i)
            <span style="height:52px;border-radius:12px;background:rgba(255,255,255,.12);position:relative;overflow:hidden">
                <span data-a="pop" data-em="{{ 1.05 + $i * .025 }}" data-dur=".18" style="position:absolute;inset:0;border-radius:12px;background:#ffb15e"></span>
            </span>
        @endforeach
    </div>
    <div data-a="pop pulsa" data-em="1.95" data-dur=".3" style="margin-top:50px"><span class="botao">Garanta a sua vaga</span></div>
    <div data-a="sobe" data-em="2.15" style="margin-top:28px"><span class="url">websight.com.br/work-flower</span></div>
    <div class="rodape">Válido para os 30 primeiros cadastros.</div>
</section>
