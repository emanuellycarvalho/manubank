<script setup>
import { ref, watch } from 'vue'
import { useChart } from '@/composables/useChart.js'
import { C } from '@/utils/chartColors.js'

const props = defineProps({
  labels:   { type: Array, default: () => [] },
  fixed:    { type: Array, default: () => [] },
  variable: { type: Array, default: () => [] },
})

const canvasRef = ref(null)

const brl = v =>
  new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v)

function hasData() {
  return props.labels.length > 0
}

function getConfig() {
  return {
    type: 'bar',
    data: {
      labels: props.labels,
      datasets: [
        {
          label: 'Fixo', data: props.fixed,
          backgroundColor: C.infoFill, borderColor: C.info,
          borderWidth: 1, borderRadius: 4, stack: 'expenses',
        },
        {
          label: 'Variável', data: props.variable,
          backgroundColor: 'rgba(252, 164, 30, 0.75)', borderColor: C.accent2,
          borderWidth: 1, borderRadius: 4, stack: 'expenses',
        },
      ],
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: { position: 'top' },
        tooltip: {
          callbacks: {
            label: ctx => ` ${ctx.dataset.label}: ${brl(ctx.parsed.y)}`,
            footer: items => `Total: ${brl(items.reduce((s, i) => s + i.parsed.y, 0))}`,
          },
        },
      },
      scales: {
        x:  { stacked: true, grid: { display: false } },
        y: {
          stacked: true, grid: { color: C.grid },
          ticks: { color: C.muted, callback: v => new Intl.NumberFormat('pt-BR', { notation: 'compact', currency: 'BRL', style: 'currency' }).format(v) },
        },
      },
    },
  }
}

const { renderChart } = useChart(canvasRef, hasData, getConfig)
watch(() => [props.labels, props.fixed, props.variable], renderChart, { deep: true })
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
}
</style>
