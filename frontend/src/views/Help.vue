<script setup>
import { ref, computed, defineAsyncComponent } from 'vue'
import { HELP_MODULES } from '@/help/modules.js'

const activeTab = ref('dashboard')

const moduleComponents = {
  dashboard: defineAsyncComponent(() => import('@/components/help/HelpDashboard.vue')),
  importar:  defineAsyncComponent(() => import('@/components/help/HelpImport.vue')),
}

const activeModule = computed(() =>
  HELP_MODULES.find((m) => m.id === activeTab.value),
)

const ActiveContent = computed(() => moduleComponents[activeTab.value] ?? null)

function selectTab(id) {
  activeTab.value = id
}
</script>

<template>
  <div class="help-page">
    <header class="page-header help-header">
      <div>
        <h2 class="page-title">Ajuda</h2>
        <p class="page-subtitle">
          Guia passo a passo de cada parte do ManuBank.
        </p>
      </div>
    </header>

    <div class="help-toolbar">
      <div class="tabs investments-tabs" role="tablist" aria-label="Módulos do aplicativo">
        <button
          v-for="mod in HELP_MODULES"
          :key="mod.id"
          type="button"
          role="tab"
          class="tab"
          :class="{ 'tab--active': activeTab === mod.id }"
          :aria-selected="activeTab === mod.id"
          :aria-controls="`help-panel-${mod.id}`"
          :id="`help-tab-${mod.id}`"
          @click="selectTab(mod.id)"
        >
          <unicon :name="mod.icon" width="16" height="16" />
          {{ mod.label }}
          <span v-if="mod.comingSoon" class="help-tab-soon">em breve</span>
        </button>
      </div>
    </div>

    <section
      class="help-content"
      role="tabpanel"
      :id="`help-panel-${activeTab}`"
      :aria-labelledby="`help-tab-${activeTab}`"
    >
      <header v-if="activeModule" class="help-content__head">
        <h3 class="help-content__title">{{ activeModule.label }}</h3>
        <p v-if="activeModule?.comingSoon && !ActiveContent" class="help-content__soon">
          O texto completo deste módulo será publicado em uma próxima atualização da Ajuda.
        </p>
      </header>

      <component :is="ActiveContent" v-if="ActiveContent" />

      <div v-else class="help-placeholder">
        <unicon name="book-open" width="40" height="40" />
        <p>
          Estamos preparando a explicação detalhada de <strong>{{ activeModule?.label }}</strong>.
        </p>
        <p class="help-placeholder__hint">
          Enquanto isso, abra esse módulo pelo menu à esquerda e explore com calma.
          Se tiver dúvidas sobre o Dashboard, volte na aba <strong>Dashboard</strong> desta página.
        </p>
      </div>
    </section>
  </div>
</template>

<style scoped>
.help-page {
  width: 100%;
}

.help-header {
  margin-bottom: 20px;
}

.help-toolbar {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px 16px;
  margin-bottom: 0;
  width: 100%;
}

.investments-tabs {
  flex: 1;
  min-width: 0;
  flex-wrap: wrap;
  overflow: visible;
  margin-bottom: 20px;
}

.investments-tabs .tab {
  white-space: nowrap;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.investments-tabs .tab:hover {
  color: var(--color-accent);
}

.investments-tabs .tab:hover :deep(svg),
.investments-tabs .tab--active :deep(svg) {
  fill: var(--color-accent);
}

.help-tab-soon {
  font-size: 0.72rem;
  font-weight: 500;
  color: var(--color-text-muted);
  text-transform: lowercase;
}

.help-content {
  width: 100%;
}

.help-content__head {
  margin-bottom: 20px;
  padding-bottom: 16px;
  border-bottom: 1px solid var(--color-border-dark);
}

.help-content__title {
  margin: 0;
  font-size: 1.35rem;
  font-weight: 700;
}

.help-content__soon {
  margin: 8px 0 0;
  font-size: 0.88rem;
  color: var(--color-text-muted);
}

.help-placeholder {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  gap: 12px;
  padding: 40px 20px;
  color: var(--color-text-muted);
  width: 100%;
}

.help-placeholder__hint {
  max-width: 36rem;
  font-size: 0.9rem;
  line-height: 1.55;
}
</style>
