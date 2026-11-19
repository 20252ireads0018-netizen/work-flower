<!doctype html>
<html lang="pt-BR">
<body style="margin:0;padding:24px;background:#f4f4f5;font-family:Arial,Helvetica,sans-serif;color:#18181b;">
    <div style="max-width:520px;margin:0 auto;background:#fff;border-radius:12px;padding:24px;border-top:4px solid #1a4585;">
        <p style="margin:0 0 4px;font-size:13px;color:#71717a;">Work Flower</p>
        @php($btn = 'display:inline-block;background:#1a4585;color:#fff;text-decoration:none;padding:11px 18px;border-radius:8px;font-size:15px;font-weight:bold;')
        @php($p = 'margin:0 0 14px;font-size:15px;line-height:1.5;')

        @if ($etapa === 'd0')
            <h1 style="margin:0 0 12px;font-size:22px;">Oi, {{ $nome }}! Sua conta está pronta.</h1>
            <p style="{{ $p }}">Comece por um passo só: <b>crie sua primeira revisão em 1 minuto</b>. Abra a aba Estudos, coloque um cartão de Flashcards e escreva uma pergunta da matéria que você mais precisa lembrar.</p>
            <p style="{{ $p }}">Daí em diante o Work Flower avisa quando é hora de revisar, sem você precisar lembrar.</p>
            <a href="{{ $estudos }}" style="{{ $btn }}">Criar minha primeira revisão</a>
        @elseif ($etapa === 'd1')
            <h1 style="margin:0 0 12px;font-size:22px;">Como o Work Flower avisa a hora de revisar</h1>
            <p style="{{ $p }}">Cada cartão de revisão volta no dia certo: logo depois de aprender, de novo alguns dias depois e cada vez mais espaçado conforme você acerta. É a revisão espaçada, o jeito mais eficiente de guardar matéria para prova.</p>
            <p style="{{ $p }}">Você só abre a aba Estudos e revisa o que estiver vencido no dia. As questões que você erra viram cartas de revisão sozinhas.</p>
            <a href="{{ $estudos }}" style="{{ $btn }}">Revisar agora</a>
        @elseif ($etapa === 'd3' && ! $usou)
            <h1 style="margin:0 0 12px;font-size:22px;">Sua primeira revisão ainda está esperando</h1>
            <p style="{{ $p }}">Leva 1 minuto: abra a aba Estudos pelo atalho abaixo, coloque um cartão de Flashcards e escreva três perguntas da matéria de amanhã. O resto o Work Flower lembra por você.</p>
            <a href="{{ $estudos }}" style="{{ $btn }}">Abrir a aba Estudos</a>
        @elseif ($etapa === 'd3')
            <h1 style="margin:0 0 12px;font-size:22px;">Próximo passo: prazos e calendário</h1>
            <p style="{{ $p }}">Você já está revisando. Agora coloque as provas, trabalhos e entregas no calendário do painel: cada prazo pode avisar por e-mail antes da hora.</p>
            <a href="{{ $calendario }}" style="{{ $btn }}">Abrir o calendário</a>
        @else
            <h1 style="margin:0 0 12px;font-size:22px;">Seu Work Flower depois do teste grátis</h1>
            <p style="{{ $p }}">
                @if ($testeAte) Seu teste grátis do plano Básico vai até <b>{{ $testeAte }}</b>. @endif
                No plano Básico você tem workflows, elementos e biblioteca sem limite, até 5 pessoas no mesmo espaço e nenhum anúncio.
                @if ($preco) Custa <b>R$ {{ number_format($preco, 2, ',', '.') }} por mês</b>, pago por Pix. @endif
            </p>
            <p style="{{ $p }}">Sem assinar, a conta continua grátis, com os limites do plano Gratuito.</p>
            <a href="{{ $planos }}" style="{{ $btn }}">Ver os planos</a>
        @endif

        <p style="margin:22px 0 0;font-size:12px;color:#a1a1aa;line-height:1.5;">
            Você recebe estes e-mails porque criou uma conta no Work Flower.
            <a href="{{ $sair }}" style="color:#a1a1aa;">Não quero mais receber estas dicas</a>.
        </p>
    </div>
</body>
</html>
