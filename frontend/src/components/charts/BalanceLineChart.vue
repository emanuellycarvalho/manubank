<script setup>
import { ref, watch } from 'vue'
import { useChart } from '@/composables/useChart.js'
import { C } from '@/utils/chartColors.js'

const props = defineProps({
  labels:      { type: Array, default: () => [] },
  incomeData:  { type: Array, default: () => [] },
  expenseData: { type: Array, default: () => [] },
})

const canvasRef = ref(null)

function hasData() {
  return props.labels.length > 0
}

function getConfig() {
  return {
    type: 'line',
    data: {
      labels: props.labels,
      datasets: [
        {
          label:           'Receitas',
          data:            props.incomeData,
          borderColor:     C.successLit,
          backgroundColor: 'rgba(0, 125, 10, 0.25)',
          borderWidth:     2.5,
          pointRadius:     4,
          pointHoverRadius: 6,
          tension:         0.35,
          fill:            true,
        },
        {
          label:           'Despesas',
          data:            props.expenseData,
          borderColor:     C.errorLit,
          backgroundColor: 'rgba(182, 0, 0, 0.35)',
          borderWidth:     2.5,
          pointRadius:     4,
          pointHoverRadius: 6,
          tension:         0.35,
          fill:            true,
        },
      ],
    },
    options: {
      responsive:          true,
      maintainAspectRatio: false,
      interaction:         { mode: 'index', intersect: false },
      plugins: {
        legend:  { position: 'top' },
        tooltip: {
          callbacks: {
            label: ctx =>
              ` ${ctx.dataset.label}: ${new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(ctx.parsed.y)}`,
          },
        },
      },
      scales: {
        x: { grid: { color: C.grid }, ticks: { color: C.muted } },
        y: {
          grid:  { color: C.grid },
          ticks: { color: C.muted,
            callback: v =>
              new Intl.NumberFormat('pt-BR', { notation: 'compact', currency: 'BRL', style: 'currency' }).format(v),
          },
        },
      },
    },
  }
}

const { renderChart } = useChart(canvasRef, hasData, getConfig)

watch(() => [props.labels, props.incomeData, props.expenseData], renderChart, { deep: true })
</script>

<template>
  <div class="chart-wrap">
    <canvas ref="canvasRef"></canvas>
    <div v-if="!hasData()" class="no-data">Sem dados para o período</div>
  </div>
</template>

<style scoped>
.chart-wrap { position: relative; height: 280px; width: 100%; }
canvas      { display: block; width: 100% !important; height: 100% !important; }
.no-data {
  position: absolute; inset: 0;
  display: flex; align-items: center; justify-content: center;
  background: var(--color-bg-secondary); color: var(--color-text-muted); font-size: .88rem;
  border-radius: 8px;
}
</style>
