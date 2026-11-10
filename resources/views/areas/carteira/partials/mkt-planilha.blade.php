{{-- Carteira › Marketplaces: importar planilha de anúncios ou de pedidos (js/marketplaces.js, plan/importarPlanilha;
     servidor em MarketplaceController::importar). $cond = expressão Alpine que mostra o formulário. --}}
<form x-show="{{ $cond }}" class="card p-4 space-y-2" @submit.prevent="importarPlanilha()">
    <h3 class="font-semibold text-sm" x-text="'Importar planilha · ' + infoCanal(plan.canal).nome"></h3>
    <p class="text-xs texto-2" x-show="plan.canal === 'shopee'">Na Central do Vendedor: <b>Meus Pedidos › Exportar</b> (pedidos) ou <b>Meus Produtos › Ferramentas em massa › Atualização em massa › Baixar</b> (anúncios). Abra o arquivo, copie as linhas com o cabeçalho e cole aqui, ou salve como CSV e escolha o arquivo.</p>
    <p class="text-xs texto-2" x-show="plan.canal === 'amazon'">No Seller Central: <b>Relatórios › Relatórios de pedidos</b> (pedidos) ou <b>Inventário › Relatórios de inventário › Relatório de todas as ofertas</b> (anúncios). O arquivo .txt separado por tabulação pode ser escolhido direto.</p>
    <p class="text-xs texto-2" x-show="plan.canal === 'mercadolivre'">Em <b>Vendas › Exportar</b> (pedidos) ou <b>Anúncios › Editar em massa › Baixar planilha</b> (anúncios).</p>
    <p class="text-xs texto-2">O Work Flower reconhece as colunas pelo nome (português ou inglês). Anúncios: SKU, Título, Preço, Estoque, Status, Custo. Pedidos: Pedido, Data, SKU, Título, Quantidade, Preço, Total, Tarifas, Status. Pedido com várias linhas (uma por item) vira um pedido só. Os dados entram na conta “planilha” do canal, que não conta no limite do plano; importar de novo atualiza o que já existe.</p>
    <textarea class="campo text-xs font-mono" rows="6" x-model="plan.texto" maxlength="2097152"
              placeholder="Pedido;Data;SKU;Título;Quantidade;Preço;Tarifas;Status&#10;2611100001;10/11/2026;CAN-AZ-01;Caneca azul 350 ml;2;49,90;19,96;Concluído"></textarea>
    <div class="flex flex-wrap items-center gap-2 text-sm">
        <label class="btn-sec !py-1.5 !px-3 cursor-pointer">Escolher arquivo…<input type="file" class="sr-only" accept=".csv,.tsv,.txt,text/csv,text/plain" @change="lerPlanilha($event)"></label>
        <span class="text-xs texto-2" x-show="plan.arquivo" x-text="plan.arquivo"></span>
        <select class="campo !w-auto !py-1.5 text-sm" x-model="plan.tipo" aria-label="Tipo da planilha">
            <option value="auto">Descobrir sozinho</option><option value="anuncios">Anúncios</option><option value="pedidos">Pedidos</option>
        </select>
        <button class="btn !py-1.5 !px-3" :disabled="ocupado">Importar</button>
        <button type="button" class="btn-sec !py-1.5 !px-3" @click="plan.canal = ''">Cancelar</button>
    </div>
</form>
