<script setup>
import { ref, watch } from 'vue'
import { useChart } from '@/composables/useChart.js'
import { C } from '@/utils/chartColors.js'

const props = defineProps({
  labels: { type: Array, default: () => [] },
  series: { type: Array, default: () => [] },
})

const canvasRef = ref(null)

const brl = v =>
  new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v)

function hasData() {
  return props.labels.length > 0 && props.series.length > 0
}

function getConfig() {
  return {
    type: 'line',
    data: {
      labels: props.labels,
      datasets: props.series.map(s => ({
        label:           s.name,
        data:            s.values,
        borderColor:     s.color,
        backgroundColor: s.color + '22',
        borderWidth:     2,
        pointRadius:     3,
        pointHoverRadius: 5,
        tension:         0.3,
        fill:            false,
      })),
    },
    options: {
      responsive:          true,
      maintainAspectRatio: false,
      interaction:         { mode: 'index', intersect: false },
      plugins: {
        legend: { position: 'right', labels: { boxWidth: 12, font: { size: 11 }, padding: 8 } },
        tooltip: { callbacks: { label: ctx => ` ${ctx.dataset.label}: ${brl(ctx.parsed.y)}` } },
      },
      scales: {
        x: { grid: { color: C.grid }, ticks: { color: C.muted } },
        y: {
          grid:  { color: C.grid },
          ticks: { color: C.muted, callback: v => new Intl.NumberFormat('pt-BR', { notation: 'compact', currency: 'BRL', style: 'currency' }).format(v) },
        },
      },
    },
  }
}

const { renderChart } = useChart(canvasRef, hasData, getConfig)
watch(() => [props.labels, props.series], renderChart, { deep: true })
</script>

<template>
  <div class="chart-wrap">
    <canvas ref="canvasRef"></canvas>
    <div v-if="!hasData()" class="no-data">Sem dados para o período</div>
  </div>
</template>

<style scoped>
.chart-wrap { position: relative; height: 300px; width: 100%; }
canvas      { display: block; width: 100% !important; height: 100% !important; }
.no-data {
  position: absolute; inset: 0;
  display: flex; align-items: center; justify-content: center;
  background: var(--color-bg-secondary); color: var(--color-text-muted); font-size: .88rem;
}
</style>
