<script setup>
import { ref, watch } from 'vue'
import { useChart } from '@/composables/useChart.js'
import { C } from '@/utils/chartColors.js'

const props = defineProps({
  labels:            { type: Array, default: () => [] },
  monthlyYield:      { type: Array, default: () => [] },
  accumulatedYield:  { type: Array, default: () => [] },
})

const canvasRef = ref(null)

const brl = (v) =>
  new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(v) || 0)

function hasData() {
  return props.labels.length > 0
}

function buildLineFill() {
  const canvas = canvasRef.value
  if (!canvas) {
    return C.emeraldFill
  }

  const ctx = canvas.getContext('2d')
  if (!ctx) {
    return C.emeraldFill
  }

  const height = canvas.height || 280
  const gradient = ctx.createLinearGradient(0, 0, 0, height)
  gradient.addColorStop(0, C.emeraldFill)
  gradient.addColorStop(1, C.emeraldFillSoft)

  return gradient
}

const compactBrl = (v) =>
  new Intl.NumberFormat('pt-BR', {
    notation: 'compact',
    currency: 'BRL',
    style: 'currency',
  }).format(v)

function getConfig() {
  return {
    type: 'line',
    data: {
      labels: props.labels,
      datasets: [
        {
          type: 'line',
          label: 'Rendimento do mês',
          data: props.monthlyYield,
          yAxisID: 'y',
          borderColor: C.emeraldLit,
          backgroundColor: buildLineFill(),
          borderWidth: 2.5,
          pointRadius: 4,
          pointHoverRadius: 6,
          pointBackgroundColor: C.emeraldLit,
          pointBorderColor: C.emerald,
          tension: 0.4,
          fill: true,
          order: 1,
        },
        {
          type: 'line',
          label: 'Acumulado',
          data: props.accumulatedYield,
          yAxisID: 'y1',
          borderColor: 'rgba(72, 86, 150, 0.75)',
          backgroundColor: 'transparent',
          borderWidth: 1.5,
          borderDash: [5, 4],
          pointRadius: 2,
          pointHoverRadius: 4,
          pointBackgroundColor: 'rgba(72, 86, 150, 0.9)',
          tension: 0.35,
          fill: false,
          order: 2,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: {
          position: 'top',
          labels: { color: C.text },
        },
        tooltip: {
          callbacks: {
            label: (ctx) => ` ${ctx.dataset.label}: ${brl(ctx.parsed.y)}`,
          },
        },
      },
      scales: {
        x: {
          grid: { color: C.grid },
          ticks: { color: C.muted, maxRotation: 45, minRotation: 0 },
        },
        y: {
          position: 'left',
          grid: { color: C.grid },
          ticks: {
            color: C.emeraldLit,
            callback: compactBrl,
          },
          title: {
            display: true,
            text: 'Mês',
            color: C.emeraldLit,
            font: { size: 11 },
          },
        },
        y1: {
          position: 'right',
          grid: { drawOnChartArea: false, color: C.grid },
          ticks: {
            color: C.muted,
            callback: compactBrl,
          },
          title: {
            display: true,
            text: 'Acumulado',
            color: C.muted,
            font: { size: 11 },
          },
        },
      },
    },
  }
}

const { renderChart } = useChart(canvasRef, hasData, getConfig)

watch(
  () => [props.labels, props.monthlyYield, props.accumulatedYield],
  renderChart,
  { deep: true },
)
</script>

<template>
  <div class="chart-wrap">
    <canvas ref="canvasRef"></canvas>
    <div v-if="!hasData()" class="no-data">Sem rendimentos no período</div>
  </div>
</template>

<style scoped>
.chart-wrap {
  position: relative;
  height: 280px;
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
