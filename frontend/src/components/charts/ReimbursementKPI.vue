<script setup>
import { ref, computed, watch } from 'vue'
import { useChart } from '@/composables/useChart.js'
import { C } from '@/utils/chartColors.js'

const props = defineProps({
  claims: { type: Array, default: () => [] },
})

const canvasRef = ref(null)

const brl = v =>
  new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v)

const openClaims    = computed(() => props.claims.filter(c => c.status === 'Aberto'))
const partialClaims = computed(() => props.claims.filter(c => c.status === 'Parcial'))
const paidClaims    = computed(() => props.claims.filter(c => c.status === 'Quitado'))

const totalOpen    = computed(() => openClaims.value.reduce((s, c) => s + c.expected_amount, 0))
const totalPartial = computed(() => partialClaims.value.reduce((s, c) => s + c.expected_amount, 0))
const totalQuitado = computed(() => paidClaims.value.reduce((s, c) => s + c.expected_amount, 0))

const totalPending = computed(() => totalOpen.value + totalPartial.value)
const totalAll     = computed(() => totalPending.value + totalQuitado.value)

function hasChartData() {
  return props.claims.length > 0
}

function getConfig() {
  return {
    type: 'doughnut',
    data: {
      labels: ['A Receber', 'Quitado'],
      datasets: [{
        data: [totalPending.value, totalQuitado.value],
        backgroundColor: ['rgba(242, 76, 0, 0.85)', C.successFill],
        borderColor: [C.accent, C.successLit],
        borderWidth: 2,
        hoverOffset: 6,
      }],
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      cutout: '65%',
      plugins: {
        legend: { position: 'bottom', labels: { boxWidth: 12, padding: 12 } },
        tooltip: {
          callbacks: {
            label: ctx => {
              const total = ctx.dataset.data.reduce((a, b) => a + b, 0)
              const pct   = total > 0 ? ((ctx.parsed / total) * 100).toFixed(1) : '0.0'
              return ` ${brl(ctx.parsed)} (${pct}%)`
            },
          },
        },
      },
    },
  }
}

const { renderChart } = useChart(canvasRef, hasChartData, getConfig)
watch(() => [props.claims, totalPending, totalQuitado], renderChart, { deep: true })
</script>

<template>
  <div class="kpi-wrap">
    <div class="kpi-grid">
      <div class="kpi-card kpi-card--pending">
        <span class="kpi-label">Pendente</span>
        <span class="kpi-value">{{ brl(totalPending) }}</span>
        <span class="kpi-sub">{{ openClaims.length }} aberto(s) · {{ partialClaims.length }} parcial(is)</span>
      </div>
      <div class="kpi-card kpi-card--paid">
        <span class="kpi-label">Quitado</span>
        <span class="kpi-value">{{ brl(totalQuitado) }}</span>
        <span class="kpi-sub">{{ paidClaims.length }} claim(s)</span>
      </div>
      <div class="kpi-card kpi-card--total">
        <span class="kpi-label">Total</span>
        <span class="kpi-value">{{ brl(totalAll) }}</span>
        <span class="kpi-sub">{{ claims.length }} claim(s)</span>
      </div>
    </div>

    <div class="doughnut-wrap">
      <canvas ref="canvasRef"></canvas>
      <div v-if="!hasChartData()" class="no-data">Nenhuma pendência de reembolso</div>
    </div>
  </div>
</template>

<style scoped>
.kpi-wrap { display: flex; flex-direction: column; gap: 16px; }

.kpi-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 10px;
}

.kpi-card {
  padding: 12px 14px;
  border-radius: 10px;
  display: flex;
  flex-direction: column;
  gap: 2px;
  background: var(--color-bg-secondary);
  border: 1px solid var(--color-border-dark);
}
.kpi-card--pending { border-left: 4px solid var(--color-accent); }
.kpi-card--paid    { border-left: 4px solid var(--color-success); }
.kpi-card--total   { border-left: 4px solid var(--color-info); }

.kpi-label { font-size: .72rem; font-weight: 600; color: var(--color-text-muted); text-transform: uppercase; letter-spacing: .4px; }
.kpi-value { font-size: 1.1rem; font-weight: 700; color: var(--color-text); }
.kpi-sub   { font-size: .72rem; color: var(--color-text-muted); }
.kpi-card--pending .kpi-value { color: var(--color-accent); }
.kpi-card--paid .kpi-value    { color: var(--color-success-text); }

.doughnut-wrap { position: relative; height: 200px; width: 100%; }
canvas         { display: block; width: 100% !important; height: 100% !important; }
.no-data {
  position: absolute; inset: 0;
  display: flex; align-items: center; justify-content: center;
  background: var(--color-bg-secondary); color: var(--color-text-muted); font-size: .88rem;
}
</style>
