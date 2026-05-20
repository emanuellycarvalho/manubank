<script setup>
const TOC = [
  { id: 'para-que-serve', label: 'Para que serve?' },
  { id: 'como-abrir', label: 'Como abrir e entender a tela' },
  { id: 'nome-perfil', label: 'Nome no perfil (menu lateral)' },
  {
    id: 'formas-importar',
    label: 'Duas formas de importar',
    children: [
      { id: 'enviar-ficheiro', label: 'Enviar ficheiro (Mercado Pago)' },
      { id: 'colar-texto', label: 'Colar texto (fatura Nubank)' },
    ],
  },
  { id: 'ao-importar', label: 'O que acontece ao clicar em Importar' },
  { id: 'painel-resultado', label: 'Painel da esquerda (resultado)' },
  { id: 'afeta-sistema', label: 'O que isso altera no ManuBank' },
  { id: 'problemas', label: 'Problemas comuns' },
  { id: 'dicas', label: 'Dicas para quem está começando' },
]
</script>

<template>
  <article class="help-article">
    <p class="help-lead">
      A tela <strong>Importar</strong> é por onde os seus movimentos bancários entram no ManuBank.
      Para o <strong>Nubank</strong>, você cola as linhas copiadas da fatura do cartão.
      Para o <strong>Mercado Pago</strong>, envia um ficheiro CSV. O sistema lê, categoriza e grava
      tudo no Extrato — sem digitar lançamento por lançamento.
    </p>

    <hr class="help-section">

    <nav class="help-toc" aria-label="Sumário desta página">
      <p class="help-toc__title">Neste guia</p>
      <ol class="help-toc__list">
        <li v-for="item in TOC" :key="item.id">
          <a class="help-toc__link" :href="`#${item.id}`">{{ item.label }}</a>
          <ol v-if="item.children?.length" class="help-toc__sublist">
            <li v-for="child in item.children" :key="child.id">
              <a class="help-toc__link" :href="`#${child.id}`">{{ child.label }}</a>
            </li>
          </ol>
        </li>
      </ol>
    </nav>

    <hr class="help-section">

    <section id="para-que-serve" class="help-section">
      <h3 class="help-h3">Para que serve?</h3>
      <ul class="help-list">
        <li>Trazer a <strong>fatura do cartão Nubank</strong> colando o texto copiado do site ou app do banco.</li>
        <li>Trazer movimentos do <strong>Mercado Pago</strong> com um ficheiro <strong>CSV</strong> exportado pelo app ou site.</li>
        <li>Aplicar automaticamente as <strong>Regras</strong> que você configurou, para sugerir categorias.</li>
        <li>Evitar trabalho manual: depois da importação, você só revisa e corrige o que for necessário no <strong>Extrato</strong>.</li>
      </ul>
      <p class="help-note help-note--info">
        Importar <strong>não apaga</strong> lançamentos antigos. Ele só <strong>adiciona</strong> linhas novas
        ou ignora as que já existem (duplicados).
      </p>
    </section>

    <section id="como-abrir" class="help-section">
      <h3 class="help-h3">Como abrir e entender a tela</h3>
      <ol class="help-steps">
        <li>No menu à esquerda, clique em <strong>Importar</strong> (ícone de seta para cima).</li>
        <li>A página divide-se em <strong>dois painéis</strong>:
          <ul class="help-list help-list--compact">
            <li><strong>Esquerda</strong> — instruções, carregamento ou resultado da última importação.</li>
            <li><strong>Direita</strong> — abas para colar texto (Nubank) ou enviar CSV (Mercado Pago), e o botão <strong>Importar</strong>.</li>
          </ul>
        </li>
        <li>Em ecrãs mais estreitos (telemóvel), o formulário aparece <strong>em cima</strong> e o resultado <strong>embaixo</strong>.</li>
      </ol>
      <p>
        No topo da página há uma frase sobre o seu nome: se já definiu o nome no perfil, o ManuBank avisa
        que Pix com esse nome na descrição serão tratados de forma especial (ver secção seguinte).
      </p>
    </section>

    <section id="nome-perfil" class="help-section">
      <h3 class="help-h3">Nome no perfil (menu lateral)</h3>
      <p>
        No menu lateral, acima dos links de navegação, há um campo com o seu nome e uma foto opcional.
        Esse nome <strong>não é obrigatório</strong>, mas é muito útil se você faz Pix entre contas suas
        (por exemplo, Nubank → Mercado Pago).
      </p>
      <ul class="help-list">
        <li>
          <strong>Com nome preenchido:</strong> durante a importação, qualquer linha cuja descrição contenha
          esse nome recebe a categoria <em>Movimentação interna</em>. Esses valores
          <strong>não entram</strong> como receita nem despesa no Dashboard.
        </li>
        <li>
          <strong>Sem nome:</strong> todos os Pix importados entram como movimentos normais, e transferências
          entre contas suas podem parecer “ganho” ou “gasto” a mais nos gráficos.
        </li>
      </ul>
      <p class="help-note help-note--warn">
        O nome deve ser como aparece na descrição do banco (ex.: “Maria Silva”), não um apelido inventado
        que o extrato não mostra.
      </p>
    </section>

    <section id="formas-importar" class="help-section">
      <h3 class="help-h3">Duas formas de importar</h3>
      <p>
        No painel da direita há duas abas. Só uma é usada de cada vez — troque de aba antes de importar.
      </p>

      <div class="help-card-block">
        <h4 id="enviar-ficheiro" class="help-h4">Enviar ficheiro (Mercado Pago)</h4>
        <p><strong>Para que serve:</strong> importar movimentos do <strong>Mercado Pago</strong> a partir de um CSV guardado no computador.</p>
        <p><strong>Formato aceite:</strong> ficheiro <strong>CSV</strong> exportado pelo Mercado Pago (movimentos Pix e outros que o extrato incluir).</p>
        <p class="help-note help-note--info">
          A fatura do <strong>Nubank</strong> não é importada por ficheiro nesta tela — use a aba
          <strong>Colar texto (Nubank)</strong>.
        </p>
        <p><strong>Passo a passo:</strong></p>
        <ol class="help-steps">
          <li>Clique na aba <strong>Enviar ficheiro</strong>.</li>
          <li>
            Na área tracejada, <strong>arraste</strong> um ou vários ficheiros ou <strong>clique</strong> na área
            para abrir a janela de escolha (pode selecionar vários CSV de uma vez).
          </li>
          <li>Confirme a lista de ficheiros. Remova um com ✕ ou use <strong>Limpar todos</strong>; <strong>+ Adicionar mais ficheiros</strong> inclui outros sem perder os já escolhidos.</li>
          <li>Clique no botão grande <strong>Importar</strong> (ou <strong>Importar N ficheiros</strong> quando houver mais de um).</li>
        </ol>
        <p class="help-note">
          Se o ficheiro não for um CSV válido do Mercado Pago, aparece erro de tipo não suportado.
        </p>
      </div>

      <div class="help-card-block">
        <h4 id="colar-texto" class="help-h4">Colar texto (fatura Nubank)</h4>
        <p>
          <strong>Para que serve:</strong> importar a <strong>fatura do cartão Nubank</strong> — copie as linhas
          no site ou app do banco e cole aqui. É o método usado para Nubank no ManuBank.
        </p>
        <ol class="help-steps">
          <li>Clique na aba <strong>Colar texto (Nubank)</strong>.</li>
          <li>
            Em <strong>Ano das transações</strong>, indique o ano correto (ex.: 2026). As linhas copiadas
            costumam trazer só dia e mês (29 MAR, 02 ABR); o sistema precisa do ano para gravar a data certa.
          </li>
          <li>Cole o texto na caixa grande (Ctrl+V ou botão direito → Colar).</li>
          <li>Confira se cada linha segue o formato esperado (exemplo abaixo).</li>
          <li>Clique em <strong>Importar</strong>.</li>
        </ol>
        <p><strong>Formato de cada linha (resumo):</strong></p>
        <p>
          <code>DD MMM •••• NNNN Descrição R$ valor</code>
        </p>
        <p class="help-note help-note--info">
          Exemplo real que o aplicativo mostra como referência:
          <code>29 MAR •••• 1470 Mercadolivre*Mercadol - Parcela 6/12 R$ 286,58</code>
        </p>
        <p>
          Parcelas (6/12), <strong>estornos</strong> (<code>Estorno de "Loja"</code> + <code>−R$</code>),
          <strong>desconto de antecipação</strong> (<code>Desconto Antecipação … −R$</code>) e compras internacionais
          costumam ser reconhecidos; estornos e descontos entram como <strong>entrada</strong> (crédito na fatura).
          Linhas incompletas podem ser <strong>ignoradas</strong>.
        </p>
      </div>
    </section>

    <section id="ao-importar" class="help-section">
      <h3 class="help-h3">O que acontece ao clicar em Importar</h3>
      <ol class="help-steps">
        <li>O botão mostra <strong>A importar…</strong> e o painel da esquerda um ícone de carregamento.</li>
        <li>
          O ManuBank lê o conteúdo (texto colado ou CSV), transforma cada movimento num lançamento e
          grava na base de dados do seu computador.
        </li>
        <li>
          Para cada linha, o motor de <strong>Regras</strong> tenta definir categoria, tipo (entrada/saída)
          e descrição traduzida — conforme o que você cadastrou em Regras.
        </li>
        <li>
          Se o seu nome no perfil aparecer na descrição, a linha vira <strong>Movimentação interna</strong>
          antes de ser guardada.
        </li>
        <li>
          <strong>Duplicados</strong> não são criados de novo: se o mesmo movimento já existir
          (mesma data, origem, operação, descrição e valor — ou mesmo ID no Mercado Pago), a linha entra
          na contagem de <strong>ignoradas</strong>.
        </li>
        <li>Linhas sem data válida ou com valor zero também são <strong>ignoradas</strong>, sem travar o resto.</li>
        <li>Ao terminar com sucesso, o formulário da direita é limpo para você poder importar outro ficheiro.</li>
      </ol>
      <p class="help-note help-note--warn">
        No CSV do Mercado Pago, por agora o sistema importa sobretudo movimentos <strong>Pix</strong>.
        Outros tipos de linha no ficheiro podem ser ignorados.
      </p>
    </section>

    <section id="painel-resultado" class="help-section">
      <h3 class="help-h3">Painel da esquerda (resultado)</h3>

      <div class="help-card-block">
        <h4 class="help-h4">Enquanto importa</h4>
        <p>Mensagem <strong>A importar transações…</strong> — aguarde; não feche a janela preta do ManuBank no computador.</p>
      </div>

      <div class="help-card-block">
        <h4 class="help-h4">Importação concluída (sucesso)</h4>
        <ul class="help-list">
          <li><strong>importadas</strong> e <strong>ignoradas</strong> — totais de todos os ficheiros (ou do texto colado).</li>
          <li><strong>Por ficheiro</strong> — quando importou vários CSV, lista o resultado de cada um (importadas/ignoradas ou mensagem de erro).</li>
          <li><strong>Importação parcial</strong> — se alguns ficheiros deram certo e outros falharam, o título avisa e o detalhe mostra qual falhou.</li>
          <li><strong>Meses processados (total)</strong> — soma por mês de todos os ficheiros importados com sucesso.</li>
        </ul>
        <p>
          O link <strong>Ver no extrato →</strong> abre o Extrato para você conferir categorias e valores.
        </p>
      </div>

      <div class="help-card-block">
        <h4 class="help-h4">Falha na importação (erro)</h4>
        <p>
          Aparece um cartão vermelho com a mensagem de erro (CSV inválido, texto mal formatado, etc.).
          Use <strong>Limpar e tentar novamente</strong>, corrija o problema e importe de novo.
        </p>
      </div>

      <div class="help-card-block">
        <h4 class="help-h4">Antes da primeira importação</h4>
        <p>
          O painel mostra o texto <strong>Como importar</strong> com os quatro passos resumidos — o mesmo fluxo
          descrito neste guia.
        </p>
      </div>
    </section>

    <section id="afeta-sistema" class="help-section">
      <h3 class="help-h3">O que isso altera no ManuBank</h3>
      <table class="help-table">
        <thead>
          <tr>
            <th scope="col">Depois de importar…</th>
            <th scope="col">Onde você percebe</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>Novos lançamentos no Extrato</td>
            <td>Menu <strong>Extrato</strong> — revise, edite categoria, marque reembolso.</td>
          </tr>
          <tr>
            <td>Categorias aplicadas pelas regras</td>
            <td>Extrato e <strong>Dashboard</strong> (gráficos por categoria, fixo/variável).</td>
          </tr>
          <tr>
            <td>Movimentação interna (nome no perfil)</td>
            <td>Dashboard e totais <strong>sem</strong> contar esses Pix como receita/despesa.</td>
          </tr>
          <tr>
            <td>Reimportar o mesmo ficheiro</td>
            <td>Mais linhas em <strong>ignoradas</strong>, quase nada em importadas — comportamento normal.</td>
          </tr>
          <tr>
            <td>Ajustar Regras depois</td>
            <td>Só afeta <strong>importações futuras</strong>; lançamentos antigos não mudam sozinhos.</td>
          </tr>
        </tbody>
      </table>
      <p class="help-note help-note--info">
        <strong>Investimentos</strong> não são alimentados por esta tela. Aportes e carteira são geridos
        em Investimentos (ou lançamentos manuais específicos, se existirem no Extrato).
      </p>
    </section>

    <section id="problemas" class="help-section">
      <h3 class="help-h3">Problemas comuns</h3>
      <ul class="help-list">
        <li>
          <strong>Botão Importar desativado</strong> — falta escolher ficheiro ou a caixa de texto está vazia.
        </li>
        <li>
          <strong>Tipo de ficheiro não suportado</strong> — na aba de ficheiro só vale CSV do Mercado Pago; Nubank é sempre pela aba de colar texto.
        </li>
        <li>
          <strong>Muitas ignoradas, poucas importadas</strong> — pode ser reimportação do mesmo extrato;
          ou linhas que o parser não reconheceu.
        </li>
        <li>
          <strong>Texto colado não importa nada</strong> — verifique o ano, o formato das linhas e se copiou
          da fatura de <em>cartão</em> Nubank (não confundir com outro tipo de extrato).
        </li>
        <li>
          <strong>Erro de rede ou servidor</strong> — a janela do <code>app.bat</code> / Terminal precisa
          estar aberta; recarregue a página (F5) e tente outra vez.
        </li>
        <li>
          <strong>Categorias “estranhas”</strong> — normal quando ainda há poucas Regras; corrija no Extrato
          e crie regras para a próxima importação.
        </li>
      </ul>
    </section>

    <section id="dicas" class="help-section">
      <h3 class="help-h3">Dicas para quem está começando</h3>
      <ol class="help-steps">
        <li>Antes da primeira importação, preencha o <strong>nome no perfil</strong> se usa Pix entre contas suas.</li>
        <li>Importe um mês de cada vez se estiver a aprender — compare o resumo com o app do banco.</li>
        <li>Abra o <strong>Extrato</strong> logo após importar e corrija duas ou três categorias erradas; depois crie <strong>Regras</strong> para não repetir o trabalho.</li>
        <li>Confira o <strong>Dashboard</strong> só depois de revisar o Extrato — assim os gráficos refletem o que você realmente quer ver.</li>
        <li>Pode reimportar o mesmo texto ou CSV sem medo: duplicados serão ignorados.</li>
      </ol>
    </section>
  </article>
</template>

<style scoped>
.help-article {
  width: 100%;
  max-width: none;
  line-height: 1.65;
  font-size: 0.95rem;
  color: var(--color-text);
}

.help-lead {
  font-size: 1.05rem;
  margin: 0 0 20px;
  color: var(--color-text);
}

.help-toc {
  margin-bottom: 28px;
}

.help-toc__title {
  margin: 0 0 10px;
  font-size: 0.95rem;
  font-weight: 600;
  color: var(--color-text);
}

.help-toc__list {
  margin: 0 0 0;
  padding-left: 1.35rem;
  list-style: decimal;
}

.help-toc__sublist {
  margin: 6px 0 0;
  padding-left: 1.35rem;
  list-style: lower-alpha;
}

.help-toc__list > li {
  margin-bottom: 6px;
}

.help-toc__link {
  color: var(--color-text);
  font-weight: inherit;
  font-size: inherit;
  text-decoration: none;
}

.help-toc__link:hover {
  color: var(--color-accent);
  text-decoration: underline;
}

.help-h3,
.help-h4 {
  scroll-margin-top: 72px;
}

.help-section {
  margin-bottom: 28px;
}

.help-h3 {
  font-size: 1.15rem;
  font-weight: 700;
  margin: 0 0 12px;
  color: var(--color-text);
}

.help-h4 {
  font-size: 1rem;
  font-weight: 700;
  margin: 20px 0 8px;
  color: var(--color-text);
}

.help-card-block {
  margin: 16px 0;
  padding: 16px 18px;
  background: var(--color-bg-primary);
  border: 1px solid var(--color-border-dark);
  border-radius: var(--radius-sm);
}

.help-card-block .help-h4 {
  margin-top: 0;
}

.help-list {
  margin: 0 0 12px;
  padding-left: 1.35rem;
}

.help-list--compact {
  margin-top: 8px;
}

.help-list li {
  margin-bottom: 6px;
}

.help-steps {
  margin: 0 0 12px;
  padding-left: 1.35rem;
}

.help-steps li {
  margin-bottom: 8px;
}

.help-note {
  margin: 12px 0 0;
  padding: 12px 14px;
  border-radius: var(--radius-sm);
  font-size: 0.9rem;
  background: var(--color-bg-primary);
  border: 1px solid var(--color-border-dark);
}

.help-note--info {
  border-left: 3px solid var(--color-info);
}

.help-note--warn {
  border-left: 3px solid var(--color-accent);
}

.help-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.88rem;
  margin: 12px 0;
}

.help-table th,
.help-table td {
  border: 1px solid var(--color-border-dark);
  padding: 10px 12px;
  text-align: left;
  vertical-align: top;
}

.help-table th {
  background: var(--color-bg-primary);
  font-weight: 600;
}

code {
  font-size: 0.88em;
  padding: 2px 6px;
  border-radius: 4px;
  background: var(--color-bg-primary);
  border: 1px solid var(--color-border-dark);
}
</style>
