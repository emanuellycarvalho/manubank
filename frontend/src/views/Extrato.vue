<script setup>
// Placeholder — rota /extrato usa Transactions.vue
</script>

<template>
  <div class="page">
    <header class="page-header">
      <h2 class="page-title">Extrato</h2>
      <p class="page-subtitle">Histórico de transações importadas</p>
    </header>

    <div class="placeholder-card panel">
      <span class="placeholder-icon">
        <unicon name="file-alt" width="48" height="48" />
      </span>
      <p>Tabela de transações e filtros serão exibidos aqui.</p>
    </div>
  </div>
</template>

<style scoped>
.page { max-width: 1100px; }

.placeholder-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  padding: 60px 40px;
  color: var(--color-text-muted);
  font-size: 1rem;
}

.placeholder-icon { display: flex; opacity: .85; }
</style>
