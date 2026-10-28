@extends('layouts.publico')

@section('titulo', 'Termos de Uso')
@section('subtitulo', 'Última atualização: 15 de outubro de 2026')

@section('texto')
<div class="space-y-4 leading-relaxed text-[0.95rem]">
    <p>
        Estes Termos de Uso regulam o acesso e a utilização do <strong>Work Flower</strong>.
        Ao criar uma conta ou usar o serviço, você concorda com as condições abaixo. Se não concordar, não utilize o serviço.
    </p>

    <h2 class="titulo text-xl pt-4">1. O serviço</h2>
    <p>
        O Work Flower é uma plataforma online de organização de trabalho e vida. Ela oferece áreas
        personalizáveis (como Corpo, Mente, Carteira e Diverso), com tarefas, anotações, tabelas, imagens e outros blocos
        que você organiza como preferir. Recursos podem ser adicionados, alterados ou removidos ao longo do tempo.
    </p>

    <h2 class="titulo text-xl pt-4">2. Conta e acesso</h2>
    <ul class="list-disc pl-6 space-y-1.5">
        <li>Para usar o serviço, você precisa criar uma conta com informações verdadeiras e mantê-las atualizadas.</li>
        <li>Você é responsável por guardar a sua senha e por tudo que acontece na sua conta.</li>
        <li>Avise-nos imediatamente se suspeitar de uso não autorizado.</li>
        <li>O serviço é destinado a maiores de 18 anos.</li>
    </ul>

    <h2 class="titulo text-xl pt-4">3. Planos e pagamentos</h2>
    <p>
        O Work Flower pode ser usado gratuitamente, com limites de uso. O plano Pró libera mais recursos e
        é cobrado de forma recorrente. Os valores, limites e condições de cada plano são informados na página de planos
        antes da contratação. Os pagamentos são processados por um provedor de pagamentos terceirizado.
        Você pode cancelar a assinatura a qualquer momento, e o cancelamento vale para as próximas cobranças.
    </p>

    <h2 class="titulo text-xl pt-4">4. Seu conteúdo</h2>
    <p>
        O conteúdo que você cria e envia continua sendo seu. Você nos autoriza apenas a armazená-lo e exibi-lo
        para você, na medida necessária para o funcionamento do serviço. Você declara ter o direito de usar
        qualquer conteúdo que enviar e se responsabiliza por ele. Recomendamos exportar periodicamente os seus dados,
        pelo recurso de importar e exportar, para manter cópias de segurança.
    </p>

    <h2 class="titulo text-xl pt-4">5. Uso aceitável</h2>
    <p>Ao usar o serviço, você se compromete a não:</p>
    <ul class="list-disc pl-6 space-y-1.5">
        <li>violar a lei ou direitos de terceiros, incluindo direitos autorais e de privacidade;</li>
        <li>enviar conteúdo ilegal, ofensivo, discriminatório ou que contenha malware;</li>
        <li>tentar acessar contas, dados ou sistemas sem autorização;</li>
        <li>sobrecarregar, interferir ou tentar burlar a segurança do serviço;</li>
        <li>copiar, revender ou explorar o serviço comercialmente sem permissão.</li>
    </ul>

    <h2 class="titulo text-xl pt-4">6. Propriedade intelectual</h2>
    <p>
        O nome Work Flower, a marca, o design, o código e os demais elementos do serviço pertencem ao seu
        titular e são protegidos pela legislação aplicável. Estes termos não transferem a você nenhum direito
        sobre eles, além do direito de usar o serviço conforme descrito aqui.
    </p>

    <h2 class="titulo text-xl pt-4">7. Disponibilidade e mudanças</h2>
    <p>
        Trabalhamos para manter o serviço disponível, mas não garantimos funcionamento ininterrupto ou livre
        de erros. Podemos realizar manutenções, atualizar ou descontinuar recursos, sempre que possível com aviso prévio.
    </p>

    <h2 class="titulo text-xl pt-4">8. Limitação de responsabilidade</h2>
    <p>
        O serviço é oferecido no estado em que se encontra. Dentro do que a lei permite, não nos responsabilizamos por
        perdas indiretas, lucros cessantes ou perda de dados decorrentes do uso ou da impossibilidade de uso do serviço.
        Nada nestes termos exclui direitos que a lei garante ao consumidor.
    </p>

    <h2 class="titulo text-xl pt-4">9. Suspensão e encerramento</h2>
    <p>
        Você pode excluir a sua conta quando quiser. Podemos suspender ou encerrar contas que violem estes termos,
        coloquem o serviço ou outros usuários em risco, ou sejam exigidas por lei.
    </p>

    <h2 class="titulo text-xl pt-4">10. Privacidade</h2>
    <p>
        O tratamento dos seus dados pessoais está descrito na
        <a href="{{ \Illuminate\Support\Facades\Route::has('privacidade') ? route('privacidade') : url('/privacidade') }}" class="link-prim">Política de Privacidade</a>,
        que faz parte destes termos.
    </p>

    <h2 class="titulo text-xl pt-4">11. Alterações nos termos</h2>
    <p>
        Podemos atualizar estes termos. A data no topo da página indica a versão mais recente. Se você continuar
        usando o serviço depois da atualização, entendemos que concorda com a nova versão.
    </p>

    <h2 class="titulo text-xl pt-4">12. Lei aplicável e contato</h2>
    <p>
        Estes termos são regidos pelas leis do Brasil. Para dúvidas, escreva para
        <a href="mailto:victorbarretophp@gmail.com" class="link-prim">victorbarretophp@gmail.com</a>
        ou use a página de
        <a href="{{ \Illuminate\Support\Facades\Route::has('contato') ? route('contato') : url('/contato') }}" class="link-prim">contato</a>.
    </p>
</div>
@endsection

