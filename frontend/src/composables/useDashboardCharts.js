import { ref, watch, onMounted } from 'vue'
import { chartsApi, reimbursementsApi, categoriesApi } from '@/services/api.js'
import { fmtPeriodLabel } from '@/utils/dates.js'

/**
 * Carrega dados de gráficos e KPI do dashboard.
 *
 * @param {import('vue').Ref<string>} startDate
 * @param {import('vue').Ref<string>} endDate
 * @param {import('vue').Ref<string>} granularity
 * @param {import('vue').Ref<number|null>} selectedCategoryId
 */
export function useDashboardCharts(startDate, endDate, granularity, selectedCategoryId) {
  const chartLabels              = ref([])
  const incomeData               = ref([])
  const expenseData              = ref([])
  const categorySlices           = ref([])
  const fixedVarLabels           = ref([])
  const fixedCostData            = ref([])
  const variableCostData         = ref([])
  const reimbursementClaims      = ref([])
  const expenseCategories        = ref([])
  const categoryEvolutionLabels  = ref([])
  const categoryEvolutionValues  = ref([])
  const categoryEvolutionName    = ref('')
  const categoryEvolutionColor   = ref('#c57700')
  const isLoadingData            = ref(false)
  const isLoadingCategoryChart   = ref(false)
  const isLoadingReimb           = ref(false)
  const dataError                = ref('')

  async function loadCategories() {
    try {
      const { data } = await categoriesApi.list()
      const list = Array.isArray(data) ? data : []
      expenseCategories.value = list
        .filter((c) => c.is_active !== 0)
        .sort((a, b) => a.name.localeCompare(b.name, 'pt-BR'))

      if (!selectedCategoryId.value && expenseCategories.value.length) {
        selectedCategoryId.value = expenseCategories.value[0].id
      }
    } catch {
      expenseCategories.value = []
    }
  }

  async function loadReimbursements() {
    isLoadingReimb.value = true
    try {
      const { data: body } = await reimbursementsApi.dashboardSummary()
      if (!body?.success) {
        throw new Error(body?.error ?? 'Resposta inválida da API de reembolsos.')
      }
      reimbursementClaims.value = body.data?.claims ?? []
    } catch (err) {
      dataError.value = dataError.value || err.message
      reimbursementClaims.value = []
    } finally {
      isLoadingReimb.value = false
    }
  }

  async function loadCategoryEvolution() {
    const start = startDate.value
    const end   = endDate.value
    const gran  = granularity.value
    const catId = selectedCategoryId.value

    if (!start || !end || start > end || !catId) {
      categoryEvolutionLabels.value = []
      categoryEvolutionValues.value = []
      return
    }

    isLoadingCategoryChart.value = true
    try {
      const { data: body } = await chartsApi.getCategoryEvolution({
        startDate: start,
        endDate: end,
        granularity: gran,
        categoryId: catId,
      })

      if (!body?.success) {
        throw new Error(body?.error ?? 'Resposta inválida da evolução por categoria.')
      }

      const series = body.data?.series ?? []
      categoryEvolutionLabels.value = series.map((row) =>
        fmtPeriodLabel(row.period_label, gran),
      )
      categoryEvolutionValues.value = series.map((row) => row.amount)
      categoryEvolutionName.value   = body.data?.category_name ?? ''
      categoryEvolutionColor.value  = body.data?.category_color ?? '#c57700'
    } catch (err) {
      dataError.value = dataError.value || err.message
      categoryEvolutionLabels.value = []
      categoryEvolutionValues.value = []
    } finally {
      isLoadingCategoryChart.value = false
    }
  }

  async function loadChartData() {
    const start = startDate.value
    const end   = endDate.value
    const gran  = granularity.value

    if (!start || !end || start > end) {
      chartLabels.value      = []
      incomeData.value       = []
      expenseData.value      = []
      categorySlices.value   = []
      fixedVarLabels.value   = []
      fixedCostData.value    = []
      variableCostData.value = []
      return
    }

    isLoadingData.value = true
    dataError.value = ''
    try {
      const [seriesRes, categoryRes, fixedVarRes] = await Promise.all([
        chartsApi.getSeries({ startDate: start, endDate: end, granularity: gran }),
        chartsApi.getExpensesByCategory({ startDate: start, endDate: end }),
        chartsApi.getFixedVsVariable({ startDate: start, endDate: end, granularity: gran }),
      ])

      const seriesBody   = seriesRes.data
      const categoryBody = categoryRes.data
      const fixedVarBody = fixedVarRes.data

      if (!seriesBody?.success) {
        throw new Error(seriesBody?.error ?? 'Resposta inválida da API de séries.')
      }
      if (!categoryBody?.success) {
        throw new Error(categoryBody?.error ?? 'Resposta inválida da API de categorias.')
      }
      if (!fixedVarBody?.success) {
        throw new Error(fixedVarBody?.error ?? 'Resposta inválida da API fixo/variável.')
      }
      const series = seriesBody.data?.series ?? []
      chartLabels.value = series.map((row) =>
        fmtPeriodLabel(row.period_label, gran),
      )
      incomeData.value  = series.map((row) => row.total_income)
      expenseData.value = series.map((row) => row.total_expenses)

      const categories = categoryBody.data?.categories ?? []
      categorySlices.value = categories.map((row) => ({
        name:   row.name,
        amount: row.amount,
        color:  row.color,
      }))

      const fvSeries = fixedVarBody.data?.series ?? []
      fixedVarLabels.value   = fvSeries.map((row) =>
        fmtPeriodLabel(row.period_label, gran),
      )
      fixedCostData.value    = fvSeries.map((row) => row.fixed)
      variableCostData.value = fvSeries.map((row) => row.variable)

    } catch (err) {
      dataError.value = err.message
      chartLabels.value      = []
      incomeData.value       = []
      expenseData.value      = []
      categorySlices.value   = []
      fixedVarLabels.value   = []
      fixedCostData.value    = []
      variableCostData.value = []
    } finally {
      isLoadingData.value = false
    }
  }

  watch([startDate, endDate, granularity], async () => {
    await loadChartData()
    await loadCategoryEvolution()
  }, { immediate: true })

  watch(selectedCategoryId, loadCategoryEvolution)

  onMounted(async () => {
    await loadCategories()
    await loadReimbursements()
    await loadCategoryEvolution()
  })

  return {
    chartLabels,
    incomeData,
    expenseData,
    categorySlices,
    fixedVarLabels,
    fixedCostData,
    variableCostData,
    reimbursementClaims,
    expenseCategories,
    categoryEvolutionLabels,
    categoryEvolutionValues,
    categoryEvolutionName,
    categoryEvolutionColor,
    isLoadingData,
    isLoadingCategoryChart,
    isLoadingReimb,
    dataError,
    loadChartData,
    loadCategoryEvolution,
  }
}
