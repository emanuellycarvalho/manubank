<script setup>
import { ref, watch, computed } from 'vue'
import { useChart } from '@/composables/useChart.js'
import { C } from '@/utils/chartColors.js'

const props = defineProps({
  labels:       { type: Array, default: () => [] },
  values:       { type: Array, default: () => [] },
  categoryName: { type: String, default: '' },
  color:        { type: String, default: C.accent },
})

const canvasRef = ref(null)

const brl = (v) =>
  new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v)

function hasData() {
  return props.labels.length > 0
}

const datasetLabel = computed(() => props.categoryName || 'Gastos')

function getConfig() {
  const lineColor = props.color || C.accent
  return {
    type: 'line',
    data: {
      labels: props.labels,
      datasets: [
        {
          label:           datasetLabel.value,
          data:            props.values,
          borderColor:     lineColor,
          backgroundColor: lineColor + '33',
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
        legend: { display: false },
        tooltip: {
          callbacks: {
            label: (ctx) => ` ${datasetLabel.value}: ${brl(ctx.parsed.y)}`,
          },
        },
      },
      scales: {
        x: { grid: { color: C.grid }, ticks: { color: C.muted } },
        y: {
          grid:  { color: C.grid },
          ticks: {
            color: C.muted,
            callback: (v) =>
              new Intl.NumberFormat('pt-BR', {
                notation: 'compact',
                currency: 'BRL',
                style: 'currency',
              }).format(v),
          },
        },
      },
    },
  }
}

const { renderChart } = useChart(canvasRef, hasData, getConfig)
watch(
  () => [props.labels, props.values, props.categoryName, props.color],
  renderChart,
  { deep: true },
)
</script>

<template>
  <div class="chart-wrap">
    <canvas ref="canvasRef"></canvas>
    <div v-if="!hasData()" class="no-data">Sem gastos desta categoria no período</div>
  </div>
</template>

<style scoped>
.chart-wrap {
  position: relative;
  height: 300px;
  width: 100%;
}

canvas {
  display: block;
  width: 100% !important;
  height: 100% !important;
}

.no-data {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--color-bg-secondary);
  color: var(--color-text-muted);
  font-size: 0.88rem;
  border-radius: 8px;
}
</style>
