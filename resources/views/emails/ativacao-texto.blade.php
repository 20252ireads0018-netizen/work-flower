Work Flower

@if ($etapa === 'd0')
Oi, {{ $nome }}! Sua conta está pronta.

Comece por um passo só: crie sua primeira revisão em 1 minuto. Abra a aba Estudos, coloque um cartão de Flashcards e escreva uma pergunta da matéria que você mais precisa lembrar. Daí em diante o Work Flower avisa quando é hora de revisar.

Criar minha primeira revisão: {{ $estudos }}
@elseif ($etapa === 'd1')
Como o Work Flower avisa a hora de revisar

Cada cartão de revisão volta no dia certo: logo depois de aprender, de novo alguns dias depois e cada vez mais espaçado conforme você acerta. Você só abre a aba Estudos e revisa o que estiver vencido no dia. As questões que você erra viram cartas de revisão sozinhas.

Revisar agora: {{ $estudos }}
@elseif ($etapa === 'd3' && ! $usou)
Sua primeira revisão ainda está esperando

Leva 1 minuto: abra a aba Estudos, coloque um cartão de Flashcards e escreva três perguntas da matéria de amanhã.

Abrir a aba Estudos: {{ $estudos }}
@elseif ($etapa === 'd3')
Próximo passo: prazos e calendário

Coloque as provas, trabalhos e entregas no calendário do painel: cada prazo pode avisar por e-mail antes da hora.

Abrir o calendário: {{ $calendario }}
@else
Seu Work Flower depois do teste grátis

@if ($testeAte)Seu teste grátis do plano Básico vai até {{ $testeAte }}. @endif
No plano Básico você tem workflows, elementos e biblioteca sem limite, até 5 pessoas no mesmo espaço e nenhum anúncio.@if ($preco) Custa R$ {{ number_format($preco, 2, ',', '.') }} por mês, pago por Pix.@endif

Sem assinar, a conta continua grátis, com os limites do plano Gratuito.

Ver os planos: {{ $planos }}
@endif

---
Você recebe estes e-mails porque criou uma conta no Work Flower.
Para não receber mais: {{ $sair }}
