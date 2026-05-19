<script setup>
import { ref, watch } from 'vue'
import { useChart } from '@/composables/useChart.js'
import { C } from '@/utils/chartColors.js'

const props = defineProps({
  steps: { type: Array, default: () => [] },
  month: { type: String, default: '' },
})

const canvasRef = ref(null)

const COLORS = {
  base:     C.muted,
  positive: C.successLit,
  negative: C.errorLit,
  result:   C.info,
}

const brl = v =>
  new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v)

function computeFloatingBars(steps) {
  let running = 0
  return steps.map(step => {
    if (step.type === 'base') {
      running = step.value
      return [0, step.value]
    }
    if (step.type === 'result') {
      return [0, running]
    }
    const prev = running
    if (step.type === 'positive') {
      running += step.value
      return [prev, running]
    }
    running -= step.value
    return [running, prev]
  })
}

function hasData() {
  return props.steps.length > 0
}

function getConfig() {
  const floats = computeFloatingBars(props.steps)
  const colors = props.steps.map(s => COLORS[s.type] ?? C.muted)
  return {
    type: 'bar',
    data: {
      labels: props.steps.map(s => s.label),
      datasets: [{
        data: floats,
        backgroundColor: colors.map(c => c + 'cc'),
        borderColor: colors,
        borderWidth: 1.5,
        borderRadius: 4,
        borderSkipped: false,
      }],
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            label: ctx => {
              const step = props.steps[ctx.dataIndex]
              if (!step) return ''
              const sign = step.type === 'negative' ? '-' : step.type === 'positive' ? '+' : ''
              return ` ${sign}${brl(step.value)}`
            },
          },
        },
      },
      scales: {
        x: { grid: { display: false } },
        y: {
          grid: { color: C.grid },
          ticks: { color: C.muted, callback: v => new Intl.NumberFormat('pt-BR', { notation: 'compact', currency: 'BRL', style: 'currency' }).format(v) },
        },
      },
    },
  }
}

const { renderChart } = useChart(canvasRef, hasData, getConfig)
watch(() => props.steps, renderChart, { deep: true })
</script>

<template>
  <div class="chart-wrap">
    <canvas ref="canvasRef"></canvas>
    <div v-if="!hasData()" class="no-data">Selecione um mês com transações</div>
  </div>
</template>

<style scoped>
.chart-wrap { position: relative; height: 280px; width: 100%; }
canvas      { display: block; width: 100% !important; height: 100% !important; }
.no-data {
  position: absolute; inset: 0;
  display: flex; align-items: center; justify-content: center;
  background: var(--color-bg-secondary); color: var(--color-text-muted); font-size: .88rem; text-align: center; padding: 20px;
}
</style>
