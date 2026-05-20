import { ref, watch } from 'vue'
import { chartsApi } from '@/services/api.js'
import { fmtPeriodLabel } from '@/utils/dates.js'

/**
 * Carrega séries agregadas do endpoint de charts para o dashboard.
 *
 * @param {import('vue').Ref<string>} startDate  YYYY-MM-DD
 * @param {import('vue').Ref<string>} endDate    YYYY-MM-DD
 * @param {import('vue').Ref<string>} granularity day | week | month | semester
 */
export function useDashboardCharts(startDate, endDate, granularity) {
  const chartLabels   = ref([])
  const incomeData    = ref([])
  const expenseData   = ref([])
  const isLoadingData = ref(false)
  const dataError     = ref('')

  async function loadChartData() {
    const start = startDate.value
    const end   = endDate.value
    const gran  = granularity.value

    if (!start || !end || start > end) {
      chartLabels.value = []
      incomeData.value  = []
      expenseData.value = []
      return
    }

    isLoadingData.value = true
    dataError.value = ''
    try {
      const { data: body } = await chartsApi.getSeries({
        startDate: start,
        endDate: end,
        granularity: gran,
      })

      if (!body?.success) {
        throw new Error(body?.error ?? 'Resposta inválida da API de gráficos.')
      }

      const series = body.data?.series ?? []
      chartLabels.value = series.map((row) =>
        fmtPeriodLabel(row.period_label, gran),
      )
      incomeData.value = series.map((row) => row.total_income)
      expenseData.value = series.map((row) => row.total_expenses)
    } catch (err) {
      dataError.value = err.message
      chartLabels.value = []
      incomeData.value  = []
      expenseData.value = []
    } finally {
      isLoadingData.value = false
    }
  }

  watch([startDate, endDate, granularity], loadChartData, { immediate: true })

  return {
    chartLabels,
    incomeData,
    expenseData,
    isLoadingData,
    dataError,
    loadChartData,
  }
}
