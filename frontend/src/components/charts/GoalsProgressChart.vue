<script setup>
import { ref, watch } from 'vue'
import { useChart } from '@/composables/useChart.js'
import { C } from '@/utils/chartColors.js'

const props = defineProps({
  goals: { type: Array, default: () => [] },
})

const canvasRef = ref(null)

const brl = v =>
  new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v)

function pct(goal) {
  if (!goal.target) return 0
  return Math.min(100, (goal.current / goal.target) * 100)
}

function hasData() {
  return props.goals.length > 0
}

function getConfig() {
  const goals     = props.goals
  const labels    = goals.map(g => g.objective)
  const filled    = goals.map(g => Math.min(g.current, g.target))
  const remaining = goals.map(g => Math.max(0, g.target - g.current))

  return {
    type: 'bar',
    data: {
      labels,
      datasets: [
        {
          label: 'Acumulado', data: filled,
          backgroundColor: C.infoFill, borderColor: C.info,
          borderWidth: 1,
          borderRadius: { topLeft: 4, bottomLeft: 4, topRight: 0, bottomRight: 0 },
          borderSkipped: false, stack: 'goals',
        },
        {
          label: 'Restante', data: remaining,
          backgroundColor: C.track, borderColor: C.muted,
          borderWidth: 1,
          borderRadius: { topLeft: 0, bottomLeft: 0, topRight: 4, bottomRight: 4 },
          borderSkipped: false, stack: 'goals',
        },
      ],
    },
    options: {
      indexAxis: 'y',
      responsive: true, maintainAspectRatio: false,
      plugins: {
        legend: { position: 'top' },
        tooltip: {
          callbacks: {
            label: ctx => {
              const g = goals[ctx.dataIndex]
              if (!g) return ''
              const p = pct(g).toFixed(1)
              if (ctx.datasetIndex === 0)
                return ` Acumulado: ${brl(g.current)} (${p}% de ${brl(g.target)})`
              return ` Restante: ${brl(Math.max(0, g.target - g.current))}`
            },
          },
        },
      },
      scales: {
        x: {
          stacked: true, grid: { color: C.grid },
          ticks: { color: C.muted, callback: v => new Intl.NumberFormat('pt-BR', { notation: 'compact', currency: 'BRL', style: 'currency' }).format(v) },
        },
        y: { stacked: true, grid: { display: false } },
      },
    },
  }
}

const { renderChart } = useChart(canvasRef, hasData, getConfig)
watch(() => props.goals, renderChart, { deep: true })
</script>

<template>
  <div class="chart-wrap">
    <canvas ref="canvasRef"></canvas>
    <div v-if="!hasData()" class="no-data">Nenhuma meta cadastrada</div>

    <div v-if="hasData()" class="goals-badges">
      <div v-for="g in goals" :key="g.objective" class="goal-badge">
        <span class="badge-name">{{ g.objective }}</span>
        <span class="badge-pct" :class="pct(g) >= 100 ? 'badge-pct--done' : 'badge-pct--prog'">
          {{ pct(g).toFixed(1) }}%
        </span>
      </div>
    </div>
  </div>
</template>

<style scoped>
.chart-wrap { position: relative; width: 100%; }
canvas      { display: block; width: 100% !important; height: 200px !important; }
.no-data {
  position: absolute; top: 0; left: 0; right: 0; height: 200px;
  display: flex; align-items: center; justify-content: center;
  background: var(--color-bg-secondary); color: var(--color-text-muted); font-size: .88rem;
}

.goals-badges {
  display: flex; flex-direction: column; gap: 4px; margin-top: 10px;
}
.goal-badge {
  display: flex; justify-content: space-between;
  font-size: .78rem; padding: 3px 4px;
}
.badge-name { color: var(--color-text); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 80%; }
.badge-pct  { font-weight: 700; flex-shrink: 0; }
.badge-pct--done { color: var(--color-success-text); }
.badge-pct--prog { color: var(--color-info); }
</style>
