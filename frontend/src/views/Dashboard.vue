<script setup>
import { ref, computed } from 'vue'
import BalanceLineChart from '@/components/charts/BalanceLineChart.vue'
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

const {
  chartLabels,
  incomeData,
  expenseData,
  isLoadingData,
  dataError,
} = useDashboardCharts(startDate, endDate, granularity)

const chartDescription = computed(() => {
  const map = {
    day:      'diária',
    week:     'semanal',
    month:    'mensal',
    semester: 'semestral',
  }
  return `Evolução ${map[granularity.value] ?? ''} de entradas e saídas no período selecionado.`
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

    <div v-if="isLoadingData" class="dash-loading">
      <span class="spinner-ui spinner-ui--sm" aria-hidden="true"></span>
      Carregando dados…
    </div>

    <div v-else class="charts-grid">
      <article class="chart-card panel">
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
