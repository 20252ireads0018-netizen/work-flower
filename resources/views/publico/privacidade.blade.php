@extends('layouts.publico')

@section('titulo', 'Política de Privacidade')
@section('subtitulo', 'Última atualização: 15 de outubro de 2026')

@section('texto')
<div class="space-y-4 leading-relaxed text-[0.95rem]">
    <p>
        Esta política explica como o <strong>Work Flower</strong> coleta, usa e protege os seus dados pessoais,
        em conformidade com a Lei Geral de Proteção de Dados (Lei nº 13.709/2018, a LGPD).
        Ao usar o site e o serviço, você declara ter lido e compreendido este texto.
    </p>

    <h2 class="titulo text-xl pt-4">1. Quem somos</h2>
    <p>
        O Work Flower é um serviço online de organização de trabalho e vida, que reúne em um só espaço
        as áreas que você mantém espalhadas em abas, aplicativos e anotações. Para qualquer assunto sobre
        privacidade, fale conosco pelo e-mail
        <a href="mailto:victorbarretophp@gmail.com" class="link-prim">victorbarretophp@gmail.com</a>.
    </p>

    <h2 class="titulo text-xl pt-4">2. Dados que coletamos</h2>
    <ul class="list-disc pl-6 space-y-1.5">
        <li><strong>Dados de cadastro:</strong> e-mail e senha. A senha é guardada de forma criptografada (hash) e não pode ser lida por nós.</li>
        <li><strong>Conteúdo que você cria:</strong> tarefas, anotações, metas, tabelas, imagens, arquivos e demais informações que você coloca nas suas áreas (Corpo, Mente, Carteira e Diverso).</li>
        <li><strong>Preferências:</strong> cores do tema, fundo da página e outras configurações de aparência.</li>
        <li><strong>Dados do plano:</strong> informações sobre o plano contratado e o estado da assinatura, quando você assina o plano Pró.</li>
        <li><strong>Dados técnicos:</strong> endereço IP, tipo de navegador, dispositivo, páginas acessadas e registros de erros, coletados automaticamente para manter o serviço seguro e funcionando.</li>
    </ul>

    <h2 class="titulo text-xl pt-4">3. Como usamos os seus dados</h2>
    <ul class="list-disc pl-6 space-y-1.5">
        <li>Criar e manter a sua conta e permitir o seu acesso.</li>
        <li>Armazenar e exibir o conteúdo que você organiza no serviço.</li>
        <li>Processar a contratação e a cobrança de planos pagos.</li>
        <li>Garantir a segurança, prevenir fraudes e corrigir falhas.</li>
        <li>Melhorar o serviço e responder às suas mensagens.</li>
        <li>Cumprir obrigações legais.</li>
    </ul>
    <p>Não vendemos os seus dados pessoais.</p>

    <h2 class="titulo text-xl pt-4">4. Cookies e tecnologias semelhantes</h2>
    <p>Usamos cookies para o site funcionar e, nas páginas públicas, para exibir anúncios:</p>
    <ul class="list-disc pl-6 space-y-1.5">
        <li><strong>Essenciais:</strong> mantêm você conectado durante a sessão e protegem formulários contra ataques. Sem eles, o serviço não funciona.</li>
        <li><strong>Lembrar de mim:</strong> se você marcar “Manter conectado neste aparelho”, um cookie permite que você continue logado depois de fechar o navegador.</li>
        <li><strong>Publicidade:</strong> cookies de terceiros usados para veicular anúncios, descritos na próxima seção.</li>
    </ul>
    <p>Você pode apagar ou bloquear cookies nas configurações do seu navegador. Isso pode afetar o funcionamento do login.</p>

    <h2 class="titulo text-xl pt-4">5. Publicidade e Google AdSense</h2>
    <p>
        Podemos exibir anúncios de terceiros, como os do Google AdSense, nas páginas públicas do site.
        A área logada do serviço não exibe anúncios.
    </p>
    <ul class="list-disc pl-6 space-y-1.5">
        <li>O Google e seus parceiros usam cookies para mostrar anúncios com base nas suas visitas anteriores a este e a outros sites.</li>
        <li>Você pode desativar a publicidade personalizada nas
            <a href="https://adssettings.google.com" target="_blank" rel="noopener" class="link-prim">Configurações de anúncios do Google</a>.
        </li>
        <li>Para saber mais sobre como o Google trata dados nesses casos, consulte
            <a href="https://policies.google.com/technologies/partner-sites" target="_blank" rel="noopener" class="link-prim">como o Google usa informações de sites parceiros</a>.
        </li>
        <li>Quando a lei exigir, pedimos o seu consentimento para cookies de publicidade por meio de uma mensagem no site.</li>
    </ul>

    <h2 class="titulo text-xl pt-4">6. Compartilhamento com terceiros</h2>
    <p>Compartilhamos dados apenas quando necessário para operar o serviço:</p>
    <ul class="list-disc pl-6 space-y-1.5">
        <li><strong>Pagamentos:</strong> a cobrança dos planos pagos é feita por um provedor de pagamentos (Asaas), que trata os dados necessários para a transação segundo a sua própria política.</li>
        <li><strong>Hospedagem e infraestrutura:</strong> os dados ficam armazenados em servidores de um provedor de hospedagem contratado por nós.</li>
        <li><strong>Publicidade:</strong> o Google AdSense, conforme descrito acima.</li>
        <li><strong>Obrigação legal:</strong> autoridades, quando houver ordem legal ou judicial.</li>
    </ul>

    <h2 class="titulo text-xl pt-4">7. Armazenamento e segurança</h2>
    <p>
        Adotamos medidas técnicas razoáveis para proteger os seus dados, como conexão segura (HTTPS),
        senhas criptografadas e controle de acesso. Nenhum sistema é totalmente imune a falhas,
        mas trabalhamos para reduzir riscos e, se houver um incidente relevante, comunicaremos os afetados e a
        autoridade competente conforme a lei. Mantemos os dados enquanto a sua conta existir ou pelo tempo
        necessário para cumprir obrigações legais.
    </p>

    <h2 class="titulo text-xl pt-4">8. Seus direitos</h2>
    <p>Pela LGPD, você pode solicitar a qualquer momento:</p>
    <ul class="list-disc pl-6 space-y-1.5">
        <li>confirmação de que tratamos seus dados e acesso a eles;</li>
        <li>correção de dados incompletos, inexatos ou desatualizados;</li>
        <li>anonimização, bloqueio ou eliminação de dados desnecessários ou tratados fora da lei;</li>
        <li>portabilidade dos dados (o serviço também permite exportar o seu conteúdo em um arquivo);</li>
        <li>eliminação dos dados tratados com o seu consentimento e revogação desse consentimento;</li>
        <li>informações sobre com quem compartilhamos os seus dados.</li>
    </ul>
    <p>
        Para exercer esses direitos, escreva para
        <a href="mailto:victorbarretophp@gmail.com" class="link-prim">victorbarretophp@gmail.com</a>.
    </p>

    <h2 class="titulo text-xl pt-4">9. Crianças e adolescentes</h2>
    <p>
        O Work Flower não é direcionado a menores de 18 anos. Se você é responsável por um menor e acredita que ele
        nos enviou dados pessoais, entre em contato para que possamos removê-los.
    </p>

    <h2 class="titulo text-xl pt-4">10. Alterações nesta política</h2>
    <p>
        Podemos atualizar esta política para refletir mudanças no serviço ou na lei. A data de atualização
        no topo da página mostra a versão mais recente. Em mudanças importantes, avisaremos pelo site ou por e-mail.
    </p>

    <h2 class="titulo text-xl pt-4">11. Contato</h2>
    <p>
        Dúvidas sobre esta política? Fale conosco pelo e-mail
        <a href="mailto:victorbarretophp@gmail.com" class="link-prim">victorbarretophp@gmail.com</a>
        ou pela página de
        <a href="{{ \Illuminate\Support\Facades\Route::has('contato') ? route('contato') : url('/contato') }}" class="link-prim">contato</a>.
    </p>
</div>
@endsection

