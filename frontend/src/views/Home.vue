<template>
  <section class="home-page">
    <div class="home-page__placeholder">
      <unicon name="estate" width="48" height="48" class="home-page__icon" />
      <h2 class="home-page__title">To do</h2>
      <p class="home-page__text">Em breve: lista de tarefas e pendências.</p>
    </div>
  </section>
</template>

<style scoped>
.home-page {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 320px;
}

.home-page__placeholder {
  text-align: center;
  color: var(--color-text-muted);
}

.home-page__icon {
  opacity: 0.5;
  margin-bottom: 16px;
}

.home-page__title {
  margin: 0 0 8px;
  font-size: 1.25rem;
  font-weight: 600;
  color: var(--color-text-primary);
}

.home-page__text {
  margin: 0;
  font-size: 0.9rem;
}
</style>
