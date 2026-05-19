<script setup>
// Dashboard — gráficos e KPIs serão implementados numa fase futura.
</script>

<template>
  <div class="dashboard">
    <header class="dash-header">
      <div>
        <h2 class="page-title">Dashboard</h2>
        <p class="page-subtitle">Visão consolidada das finanças</p>
      </div>
    </header>

    <div class="todo-card panel">
      <span class="todo-card__icon" aria-hidden="true">
        <unicon name="chart-pie" width="48" height="48" />
      </span>
      <h3 class="todo-card__title">Em breve</h3>
      <p class="todo-card__text">
        Esta página terá gráficos de receitas, despesas, evolução por categoria,
        investimentos e reembolsos. Por agora use o <RouterLink to="/extrato">extrato</RouterLink>
        e a página de <RouterLink to="/importar">importação</RouterLink>.
      </p>
    </div>
  </div>
</template>

<style scoped>
.dashboard {
  width: 100%;
}

.dash-header {
  margin-bottom: 24px;
}

.todo-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  gap: 12px;
  padding: 64px 40px;
  max-width: 520px;
}

.todo-card__icon {
  display: flex;
  line-height: 1;
  opacity: 0.9;
}

.todo-card__title {
  font-size: 1.2rem;
  font-weight: 700;
  color: var(--color-text);
  margin: 0;
}

.todo-card__text {
  font-size: .95rem;
  color: var(--color-text-muted);
  line-height: 1.6;
  margin: 0;
}

.todo-card__text a {
  color: var(--color-accent);
  font-weight: 600;
  text-decoration: none;
}

.todo-card__text a:hover {
  color: var(--color-accent-hover);
  text-decoration: underline;
}
</style>
