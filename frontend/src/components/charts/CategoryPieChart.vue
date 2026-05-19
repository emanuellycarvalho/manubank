<script setup>
import { ref, watch } from 'vue'
import { useChart } from '@/composables/useChart.js'
import { C } from '@/utils/chartColors.js'

const props = defineProps({
  slices: { type: Array, default: () => [] },
})

const canvasRef = ref(null)

const brl = v =>
  new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v)

function hasData() {
  return props.slices.some(s => s.amount > 0)
}

function getConfig() {
  const sorted = [...props.slices].sort((a, b) => b.amount - a.amount)
  return {
    type: 'pie',
    data: {
      labels:   sorted.map(s => s.name),
      datasets: [{
        data:            sorted.map(s => s.amount),
        backgroundColor: sorted.map(s => s.color),
        borderColor:     C.sliceBorder,
        borderWidth:     2,
        hoverOffset:     8,
      }],
    },
    options: {
      responsive:          true,
      maintainAspectRatio: false,
      plugins: {
        legend: { position: 'right', labels: { boxWidth: 12, font: { size: 11 }, padding: 8 } },
        tooltip: {
          callbacks: {
            label: ctx => {
              const total = ctx.dataset.data.reduce((a, b) => a + b, 0)
              const pct   = total > 0 ? ((ctx.parsed / total) * 100).toFixed(1) : '0'
              return ` ${brl(ctx.parsed)} (${pct}%)`
            },
          },
        },
      },
    },
  }
}

const { renderChart } = useChart(canvasRef, hasData, getConfig)
watch(() => props.slices, renderChart, { deep: true })
</script>

<template>
  <div class="chart-wrap">
    <canvas ref="canvasRef"></canvas>
    <div v-if="!hasData()" class="no-data">Sem despesas no período</div>
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
