<script setup>
import { ref, watch } from 'vue'
import { useChart } from '@/composables/useChart.js'
import { C } from '@/utils/chartColors.js'

const props = defineProps({
  labels:     { type: Array, default: () => [] },
  series:     { type: Array, default: () => [] },
  targetLine: { type: Number, default: 4000 },
})

const canvasRef = ref(null)

const brl = v =>
  new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v)

function barColor(item) {
  if (item?.patternBroken)  return C.errorFill
  if (item?.isExtraSurplus) return C.successFill
  return C.infoFill
}

function barBorder(item) {
  if (item?.patternBroken)  return C.errorLit
  if (item?.isExtraSurplus) return C.successLit
  return C.info
}

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
          type: 'bar', label: 'Investido',
          data: props.series.map(s => s.actual),
          backgroundColor: props.series.map(barColor),
          borderColor: props.series.map(barBorder),
          borderWidth: 1.5, borderRadius: 4, order: 2,
        },
        {
          type: 'line',
          label: `Meta mínima (${brl(props.targetLine)})`,
          data: props.labels.map(() => props.targetLine),
          borderColor: C.accent, borderWidth: 2, borderDash: [6, 4],
          pointRadius: 0, fill: false, order: 1,
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
            label: ctx => {
              if (ctx.datasetIndex === 0) {
                const s = props.series[ctx.dataIndex]
                const tag = s?.patternBroken ? ' (!)' : s?.isExtraSurplus ? ' (+)' : ''
                return ` Investido: ${brl(ctx.parsed.y)}${tag}`
              }
              return ` Meta: ${brl(ctx.parsed.y)}`
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
watch(() => [props.labels, props.series, props.targetLine], renderChart, { deep: true })
</script>

<template>
  <div class="chart-wrap">
    <canvas ref="canvasRef"></canvas>
    <div v-if="!hasData()" class="no-data">Nenhum fechamento salvo no período</div>
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
