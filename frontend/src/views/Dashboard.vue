<script setup>
import { ref, computed } from 'vue'
import BalanceLineChart from '@/components/charts/BalanceLineChart.vue'
import CategoryPieChart from '@/components/charts/CategoryPieChart.vue'
import FixedVsVariableChart from '@/components/charts/FixedVsVariableChart.vue'
import ReimbursementKPI from '@/components/charts/ReimbursementKPI.vue'
import CategoryEvolutionLineChart from '@/components/charts/CategoryEvolutionLineChart.vue'
import DateRangePicker from '@/components/DateRangePicker.vue'
import { useDashboardCharts } from '@/composables/useDashboardCharts.js'
import { defaultDashboardDateRange } from '@/utils/dates.js'

const GRANULARITY_OPTIONS = [
  { value: 'day',      label: 'Dia' },
  { value: 'week',     label: 'Semana' },
  { value: 'month',    label: 'Mês' },
  { value: 'semester', label: 'Semestre' },
]

const defaults = defaultDashboardDateRange()
const startDate   = ref(defaults.start)
const endDate     = ref(defaults.end)
const granularity = ref('month')
const selectedCategoryId = ref(null)

const {
  chartLabels,
  incomeData,
  expenseData,
  categorySlices,
  fixedVarLabels,
  fixedCostData,
  variableCostData,
  reimbursementClaims,
  expenseCategories,
  categoryEvolutionLabels,
  categoryEvolutionValues,
  categoryEvolutionName,
  categoryEvolutionColor,
  isLoadingData,
  isLoadingCategoryChart,
  isLoadingReimb,
  dataError,
} = useDashboardCharts(startDate, endDate, granularity, selectedCategoryId)

const isLoading = computed(
  () => isLoadingData.value || isLoadingReimb.value || isLoadingCategoryChart.value,
)

const chartDescription = computed(() => {
  const map = {
    day:      'diária',
    week:     'semanal',
    month:    'mensal',
    semester: 'semestral',
  }
  return `Evolução ${map[granularity.value] ?? ''} de entradas e saídas no período selecionado.`
})

const fixedVarDescription = computed(() => {
  const map = {
    day:      'diária',
    week:     'semanal',
    month:    'mensal',
    semester: 'semestral',
  }
  return `Custos fixos (ex.: assinaturas, saúde) vs. variáveis (ex.: lazer, comer fora) — visão ${map[granularity.value] ?? ''}.`
})

function onDateRange({ start, end }) {
  startDate.value = start
  endDate.value   = end
}
</script>

<template>
  <div class="dashboard">
    <header class="page-header dash-header">
      <div>
        <h2 class="page-title">Dashboard</h2>
        <p class="page-subtitle">Visão consolidada das finanças</p>
      </div>
    </header>

    <section class="dash-filters panel" aria-label="Filtros do dashboard">
      <div class="dash-filters__row">
        <DateRangePicker
          label="Período"
          :start="startDate"
          :end="endDate"
          @update:date-range="onDateRange"
        />

        <div class="granularity-field">
          <label class="granularity-field__label" for="dash-granularity">Visão</label>
          <select
            id="dash-granularity"
            v-model="granularity"
            class="app-select granularity-field__select"
          >
            <option
              v-for="opt in GRANULARITY_OPTIONS"
              :key="opt.value"
              :value="opt.value"
            >
              {{ opt.label }}
            </option>
          </select>
        </div>
      </div>

      <p v-if="dataError" class="dash-filters__warn">
        {{ dataError }}
      </p>
    </section>

    <div v-if="isLoading" class="dash-loading">
      <span class="spinner-ui spinner-ui--sm" aria-hidden="true"></span>
      Carregando dados…
    </div>

    <div v-else class="charts-grid">
      <article class="chart-card panel chart-card--balance">
        <header class="chart-card__header">
          <h3 class="chart-card__title">Balanço — Receitas vs. Despesas</h3>
          <p class="chart-card__desc">{{ chartDescription }}</p>
        </header>
        <BalanceLineChart
          :labels="chartLabels"
          :income-data="incomeData"
          :expense-data="expenseData"
        />
      </article>

      <article class="chart-card panel chart-card--composition">
        <header class="chart-card__header">
          <h3 class="chart-card__title">Composição — Gastos por categoria</h3>
          <p class="chart-card__desc">
            Distribuição das despesas por categoria no período selecionado.
          </p>
        </header>
        <CategoryPieChart :slices="categorySlices" />
      </article>

      <article class="chart-card panel chart-card--fixed-var">
        <header class="chart-card__header">
          <h3 class="chart-card__title">Custos — Fixo vs. Variável</h3>
          <p class="chart-card__desc">{{ fixedVarDescription }}</p>
        </header>
        <FixedVsVariableChart
          :labels="fixedVarLabels"
          :fixed="fixedCostData"
          :variable="variableCostData"
        />
      </article>

      <article class="chart-card panel chart-card--reimbursement">
        <header class="chart-card__header">
          <h3 class="chart-card__title">Reembolsos</h3>
          <p class="chart-card__desc">
            Valores a receber (pendentes) versus já quitados.
          </p>
        </header>
        <ReimbursementKPI :claims="reimbursementClaims" />
      </article>

      <article class="chart-card panel chart-card--category-evolution">
        <header class="chart-card__header chart-card__header--toolbar">
          <div class="chart-card__intro">
            <h3 class="chart-card__title">Categorias — Evolução de gastos</h3>
            <p class="chart-card__desc">
              Despesas da categoria selecionada ao longo do período (uma categoria por vez).
            </p>
          </div>
          <div class="category-filter">
            <label class="category-filter__label" for="dash-category">Categoria</label>
            <select
              id="dash-category"
              v-model="selectedCategoryId"
              class="app-select category-filter__select"
              :disabled="!expenseCategories.length"
            >
              <option
                v-for="cat in expenseCategories"
                :key="cat.id"
                :value="cat.id"
              >
                {{ cat.name }}
              </option>
            </select>
          </div>
        </header>
        <CategoryEvolutionLineChart
          :labels="categoryEvolutionLabels"
          :values="categoryEvolutionValues"
          :category-name="categoryEvolutionName"
          :color="categoryEvolutionColor"
        />
      </article>
    </div>
  </div>
</template>

<style scoped>
.dashboard {
  width: 100%;
}

.dash-header {
  margin-bottom: 20px;
}

.dash-filters {
  padding: 18px 20px;
  margin-bottom: 24px;
}

.dash-filters__row {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  gap: 16px 24px;
}

.granularity-field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.granularity-field__label {
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--color-text-muted);
}

.granularity-field__select {
  min-width: 140px;
}

.dash-filters__warn {
  margin: 12px 0 0;
  font-size: 0.85rem;
  color: var(--color-error-text);
}

.dash-loading {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 32px;
  color: var(--color-text-muted);
  font-size: 0.9rem;
}

.charts-grid {
  display: grid;
  gap: 20px;
  grid-template-columns: 1fr;
}

@media (min-width: 960px) {
  .charts-grid {
    grid-template-columns: 1fr 1fr;
    align-items: start;
  }

  /* Linha 1: balanço em largura total */
  .chart-card--balance {
    grid-column: 1 / -1;
  }

  /* Linha 2: pizza à esquerda; direita vazia */
  .chart-card--composition {
    grid-column: 1;
  }

  /* Linha 3: fixo/variável à esquerda, reembolsos à direita */
  .chart-card--fixed-var {
    grid-column: 1;
  }

  .chart-card--reimbursement {
    grid-column: 2;
  }

  /* Linha 4: evolução por categoria em largura total */
  .chart-card--category-evolution {
    grid-column: 1 / -1;
  }
}

.chart-card__header--toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  justify-content: space-between;
  gap: 16px;
}

.chart-card__intro {
  flex: 1;
  min-width: 200px;
}

.category-filter {
  display: flex;
  flex-direction: column;
  gap: 6px;
  flex-shrink: 0;
}

.category-filter__label {
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--color-text-muted);
}

.category-filter__select {
  min-width: 200px;
}

.chart-card {
  padding: 20px;
}

.chart-card__header {
  margin-bottom: 16px;
}

.chart-card__title {
  font-size: 1.05rem;
  font-weight: 700;
  color: var(--color-text);
  margin: 0 0 4px;
}

.chart-card__desc {
  font-size: 0.85rem;
  color: var(--color-text-muted);
  margin: 0;
}
</style>
