<script setup>
const TOC = [
  { id: 'para-que-serve', label: 'Para que serve?' },
  { id: 'como-abrir', label: 'Como abrir o Dashboard' },
  {
    id: 'filtros',
    label: 'Filtros no topo da página',
    children: [
      { id: 'filtro-periodo', label: 'Período' },
      { id: 'filtro-visao', label: 'Visão (Dia, Semana, Mês, Semestre)' },
    ],
  },
  { id: 'de-onde-vem', label: 'De onde vêm os números?' },
  {
    id: 'graficos',
    label: 'Gráficos e cartões (um a um)',
    children: [
      { id: 'grafico-balanco', label: 'Balanço — Receitas vs. Despesas' },
      { id: 'grafico-composicao', label: 'Composição — Gastos por categoria' },
      { id: 'grafico-fixo-variavel', label: 'Custos — Fixo vs. Variável' },
      { id: 'grafico-reembolsos', label: 'Reembolsos' },
      { id: 'grafico-evolucao', label: 'Categorias — Evolução de gastos' },
    ],
  },
  { id: 'carregando-erros', label: '“Carregando dados…” e mensagens de erro' },
  { id: 'dicas', label: 'Dicas para quem está começando' },
]
</script>

<template>
  <article class="help-article">
    <p class="help-lead">
      O <strong>Dashboard</strong> é a primeira tela do ManuBank. Ele reúne gráficos e resumos
      das suas finanças para você enxergar o quadro geral — sem precisar abrir cada lançamento
      no Extrato.
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
        <li>Ver se, no período escolhido, você <strong>ganhou mais do que gastou</strong> (ou o contrário).</li>
        <li>Descobrir <strong>em que categorias</strong> o dinheiro foi parar (alimentação, transporte, lazer…).</li>
        <li>Comparar gastos <strong>fixos</strong> (que se repetem todo mês) com <strong>variáveis</strong> (que mudam).</li>
        <li>Acompanhar <strong>reembolsos</strong> que você deve receber de outras pessoas.</li>
        <li>Observar como os gastos de <strong>uma categoria</strong> evoluíram ao longo do tempo.</li>
      </ul>
      <p class="help-note help-note--info">
        O Dashboard é só para <strong>consultar</strong>. Nenhum botão aqui altera seus lançamentos,
        categorias ou investimentos. Para mudar dados, use Importar, Extrato, Categorias ou Regras.
      </p>
    </section>

    <section id="como-abrir" class="help-section">
      <h3 class="help-h3">Como abrir o Dashboard</h3>
      <ol class="help-steps">
        <li>Abra o ManuBank no navegador (por exemplo, após clicar em <code>app.bat</code> no Windows).</li>
        <li>No menu à esquerda, clique em <strong>Dashboard</strong> (ícone de gráfico).</li>
        <li>Ao abrir o aplicativo, você já cai nesta tela automaticamente.</li>
      </ol>
    </section>

    <section id="filtros" class="help-section">
      <h3 class="help-h3">Filtros no topo da página</h3>
      <p>
        Antes dos gráficos há uma faixa de filtros. Eles definem <em>quais datas</em> entram na maioria
        dos gráficos e <em>como o tempo é agrupado</em> no eixo horizontal.
      </p>

      <h4 id="filtro-periodo" class="help-h4">Período</h4>
      <ol class="help-steps">
        <li>Clique no campo <strong>Período</strong> (mostra duas datas, por exemplo <em>01/12/2025 – 20/05/2026</em>).</li>
        <li>Abre um painel com atalhos à esquerda e calendário à direita.</li>
        <li>Escolha um atalho rápido, se quiser:
          <ul class="help-list help-list--compact">
            <li><strong>Hoje</strong> — só o dia atual.</li>
            <li><strong>Últimos 7 dias</strong> ou <strong>Últimos 30 dias</strong>.</li>
            <li><strong>Mês atual</strong> ou <strong>Mês passado</strong>.</li>
            <li><strong>Este ano</strong> — de 1º de janeiro até hoje.</li>
            <li><strong>Tudo</strong> — desde 2000 até hoje (pode demorar um pouco se houver muitos lançamentos).</li>
          </ul>
        </li>
        <li>Ou marque <strong>data inicial</strong> e <strong>data final</strong> no calendário e confirme.</li>
        <li>Os gráficos recarregam sozinhos (aparece “Carregando dados…” por instantes).</li>
      </ol>
      <p class="help-note help-note--info">
        Ao abrir o Dashboard pela primeira vez no dia, o período padrão é os
        <strong>últimos 6 meses</strong> (do primeiro dia do mês, seis meses atrás, até hoje).
      </p>

      <h4 id="filtro-visao" class="help-h4">Visão (Dia, Semana, Mês, Semestre)</h4>
      <p>
        O menu <strong>Visão</strong> não muda <em>quais</em> lançamentos entram — só muda
        <em>como eles são agrupados</em> no tempo:
      </p>
      <ul class="help-list">
        <li><strong>Dia</strong> — cada ponto no gráfico é um dia. Bom para poucos dias ou para detalhar uma semana.</li>
        <li><strong>Semana</strong> — agrupa por semana do ano. Útil para hábitos semanais.</li>
        <li><strong>Mês</strong> — um ponto por mês (padrão recomendado para visão geral).</li>
        <li><strong>Semestre</strong> — dois blocos por ano (1º e 2º semestre). Bom para comparar metades do ano.</li>
      </ul>
      <p>
        Se a data inicial for posterior à final, os gráficos ficam vazios até você corrigir o intervalo.
      </p>
    </section>

    <section id="de-onde-vem" class="help-section">
      <h3 class="help-h3">De onde vêm os números?</h3>
      <p>
        Tudo que aparece no Dashboard é calculado a partir dos <strong>lançamentos do Extrato</strong>
        (entradas, saídas e rendimentos importados ou criados manualmente), usando as
        <strong>categorias</strong> de cada movimento.
      </p>
      <table class="help-table">
        <thead>
          <tr>
            <th scope="col">Se você…</th>
            <th scope="col">O Dashboard…</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>Importa um extrato em <strong>Importar</strong></td>
            <td>Passa a mostrar esses valores depois que os lançamentos existirem no Extrato.</td>
          </tr>
          <tr>
            <td>Altera categoria ou valor no <strong>Extrato</strong></td>
            <td>Atualiza os gráficos na próxima vez que a página carregar ou quando você mudar os filtros.</td>
          </tr>
          <tr>
            <td>Cria ou edita <strong>Categorias</strong></td>
            <td>Muda nomes, cores e a divisão “fixo vs. variável” nos gráficos de composição e evolução.</td>
          </tr>
          <tr>
            <td>Configura <strong>Regras</strong></td>
            <td>Em importações futuras, categorias podem ser aplicadas automaticamente — e o Dashboard reflete isso.</td>
          </tr>
          <tr>
            <td>Marca lançamentos como <strong>reembolso</strong> no Extrato</td>
            <td>Alimenta o bloco de Reembolsos (independente do período escolhido nos filtros).</td>
          </tr>
          <tr>
            <td>Preenche seu <strong>nome no perfil</strong> (menu lateral)</td>
            <td>
              Na importação, descrições com esse nome viram <em>Movimentação interna</em> (Pix entre suas contas).
              Esses valores <strong>não entram</strong> em receitas nem despesas dos gráficos principais.
            </td>
          </tr>
        </tbody>
      </table>
      <p class="help-note">
        <strong>Investimentos</strong> (aportes, resgates, carteira) têm tela própria. O Dashboard foca no
        fluxo do dia a dia (conta corrente / cartão), não no patrimônio investido — exceto quando um
        lançamento no Extrato é do tipo <em>rendimento</em>, que entra nos cálculos internos da API,
        mas o gráfico principal de balanço mostra apenas <strong>Receitas</strong> e <strong>Despesas</strong>.
      </p>
    </section>

    <section id="graficos" class="help-section">
      <h3 class="help-h3">Gráficos e cartões (um a um)</h3>

      <div class="help-card-block">
        <h4 id="grafico-balanco" class="help-h4">1. Balanço — Receitas vs. Despesas</h4>
        <p><strong>O que mostra:</strong> duas linhas ao longo do tempo — verde (entradas) e vermelha/laranja (saídas).</p>
        <p><strong>Como ler:</strong></p>
        <ul class="help-list">
          <li>Quando a linha de receitas está <strong>acima</strong> da de despesas naquele período, você entrou mais dinheiro do que saiu.</li>
          <li>Passe o mouse sobre um ponto (se o seu dispositivo permitir) para ver o valor exato em reais.</li>
        </ul>
        <p><strong>O que conta como receita:</strong> lançamentos do tipo <em>entrada</em> no Extrato, no intervalo e com categoria diferente de Movimentação interna.</p>
        <p><strong>O que conta como despesa:</strong> lançamentos do tipo <em>saída</em>, com a mesma regra de exclusão.</p>
      </div>

      <div class="help-card-block">
        <h4 id="grafico-composicao" class="help-h4">2. Composição — Gastos por categoria</h4>
        <p><strong>O que mostra:</strong> um gráfico de “fatias” (pizza) com cada fatia = uma categoria de <em>despesa</em>.</p>
        <p><strong>Como ler:</strong> fatias maiores = mais dinheiro gasto naquela categoria no período. As cores seguem as definidas em Categorias.</p>
        <p><strong>Importante:</strong> só entram <strong>saídas</strong> categorizadas. Receitas não aparecem neste gráfico. Categorias sem gasto no período podem não aparecer.</p>
      </div>

      <div class="help-card-block">
        <h4 id="grafico-fixo-variavel" class="help-h4">3. Custos — Fixo vs. Variável</h4>
        <p>
          <strong>O que mostra:</strong> barras empilhadas ou lado a lado comparando dois tipos de custo,
          usando a classificação de cada <strong>categoria</strong> (fixa ou variável).
        </p>
        <p><strong>Exemplos do próprio aplicativo:</strong></p>
        <ul class="help-list">
          <li><strong>Fixo</strong> — assinaturas, plano de saúde, aluguel (gastos que tendem a repetir).</li>
          <li><strong>Variável</strong> — lazer, restaurantes, compras que mudam de mês para mês.</li>
        </ul>
        <p>
          Se uma categoria estiver marcada errada em Categorias, este gráfico ficará distorcido até você corrigir lá.
        </p>
      </div>

      <div class="help-card-block">
        <h4 id="grafico-reembolsos" class="help-h4">4. Reembolsos</h4>
        <p>
          <strong>O que mostra:</strong> quanto você ainda tem a <strong>receber</strong> de outras pessoas
          (pendente + parcial) versus o que já foi <strong>quitado</strong>.
        </p>
        <p><strong>Como usar na prática:</strong> lembre-se de marcar no Extrato os gastos que outra pessoa vai pagar
          e acompanhar se o status mudou para Quitado quando o dinheiro caiu na conta.</p>
        <p class="help-note help-note--warn">
          Este bloco <strong>não obedece</strong> ao filtro de Período do topo. Ele lista todos os reembolsos
          cadastrados no sistema, para você não perder nenhum valor em aberto só porque a data do gasto
          é antiga.
        </p>
        <p><strong>Status possíveis:</strong></p>
        <ul class="help-list">
          <li><strong>Aberto</strong> — nada foi recebido ainda.</li>
          <li><strong>Parcial</strong> — recebeu uma parte do valor esperado.</li>
          <li><strong>Quitado</strong> — valor totalmente recebido.</li>
        </ul>
      </div>

      <div class="help-card-block">
        <h4 id="grafico-evolucao" class="help-h4">5. Categorias — Evolução de gastos</h4>
        <p>
          <strong>O que mostra:</strong> uma linha com a soma das <strong>despesas</strong> de
          <em>uma única categoria</em> por período (dia, semana, mês ou semestre — conforme Visão).
        </p>
        <ol class="help-steps">
          <li>No canto do cartão, abra o menu <strong>Categoria</strong>.</li>
          <li>Escolha a categoria que quer estudar (só aparecem categorias ativas de despesa).</li>
          <li>Compare a linha ao longo do tempo: subidas = gastou mais naquele intervalo.</li>
        </ol>
        <p>
          A cor da linha é a mesma da categoria. Se o menu estiver desabilitado, ainda não há categorias
          de despesa cadastradas — crie-as em Categorias primeiro.
        </p>
      </div>
    </section>

    <section id="carregando-erros" class="help-section">
      <h3 class="help-h3">“Carregando dados…” e mensagens de erro</h3>
      <ul class="help-list">
        <li>
          <strong>Carregando dados…</strong> — normal ao mudar período ou visão; o aplicativo está buscando
          informações no seu computador (não na internet).
        </li>
        <li>
          <strong>Mensagem em vermelho</strong> abaixo dos filtros — algo falhou (por exemplo, servidor
          fechado ou arquivo de banco indisponível). Verifique se a janela do <code>app.bat</code> ou do
          <code>make app</code> ainda está aberta e recarregue a página do navegador (F5).
        </li>
        <li>
          <strong>Gráficos vazios</strong> — pode ser período sem lançamentos, só movimentação interna,
          ou ainda não ter importado extrato. Confira o Extrato com o mesmo período.
        </li>
      </ul>
    </section>

    <section id="dicas" class="help-section">
      <h3 class="help-h3">Dicas para quem está começando</h3>
      <ol class="help-steps">
        <li>Importe pelo menos um extrato em <strong>Importar</strong> e confira se os lançamentos aparecem no <strong>Extrato</strong>.</li>
        <li>Volte ao Dashboard com <strong>Visão: Mês</strong> e período <strong>Este ano</strong> para uma foto anual simples.</li>
        <li>Use o gráfico de <strong>Composição</strong> para achar onde cortar gastos; use <strong>Fixo vs. Variável</strong> para ver o que é difícil reduzir de imediato.</li>
        <li>Se transferir dinheiro entre suas próprias contas, cadastre seu nome no perfil para não inflar receitas e despesas.</li>
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

.help-section--related {
  padding-top: 8px;
  border-top: 1px solid var(--color-border-dark);
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
