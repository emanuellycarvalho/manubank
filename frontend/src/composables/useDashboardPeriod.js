import { ref, computed, watch } from 'vue'
import { transactionsApi } from '@/services/api.js'
import {
  compareMonthYear,
  monthsBetween,
  shiftMonthYear,
  toMonthYear,
} from '@/utils/dates.js'

const MONTHS = [
  { value: '01', label: 'Janeiro' },
  { value: '02', label: 'Fevereiro' },
  { value: '03', label: 'Março' },
  { value: '04', label: 'Abril' },
  { value: '05', label: 'Maio' },
  { value: '06', label: 'Junho' },
  { value: '07', label: 'Julho' },
  { value: '08', label: 'Agosto' },
  { value: '09', label: 'Setembro' },
  { value: '10', label: 'Outubro' },
  { value: '11', label: 'Novembro' },
  { value: '12', label: 'Dezembro' },
]

function splitMonthYear(monthYear) {
  const match = /^(\d{4})-(\d{2})$/.exec(monthYear ?? '')
  if (!match) return { year: '', month: '' }
  return { year: match[1], month: match[2] }
}

/**
 * Filtro global de período (mês/ano inicial e final) para o dashboard.
 */
export function useDashboardPeriod() {
  const availableMonths = ref([])
  const startMonth = ref('01')
  const startYear  = ref('')
  const endMonth   = ref('12')
  const endYear    = ref('')
  const isLoadingMonths = ref(false)
  const monthsError = ref('')

  const years = computed(() => {
    const set = new Set(availableMonths.value.map((m) => m.slice(0, 4)))
    return [...set].sort((a, b) => Number(b) - Number(a))
  })

  const startMonthYear = computed(() =>
    startYear.value && startMonth.value
      ? toMonthYear(startYear.value, startMonth.value)
      : '',
  )

  const endMonthYear = computed(() =>
    endYear.value && endMonth.value
      ? toMonthYear(endYear.value, endMonth.value)
      : '',
  )

  const monthsInRange = computed(() =>
    monthsBetween(startMonthYear.value, endMonthYear.value),
  )

  const periodInvalid = computed(
    () =>
      Boolean(startMonthYear.value && endMonthYear.value) &&
      compareMonthYear(startMonthYear.value, endMonthYear.value) > 0,
  )

  function setPeriodFromMonthYear(start, end) {
    const s = splitMonthYear(start)
    const e = splitMonthYear(end)
    startMonth.value = s.month
    startYear.value  = s.year
    endMonth.value   = e.month
    endYear.value    = e.year
  }

  function defaultPeriod(months) {
    if (!months.length) return
    const latest = months[0]
    const earliest = months[months.length - 1]
    const span = Math.min(5, months.length - 1)
    const start = shiftMonthYear(latest, -span)
    const clampedStart =
      compareMonthYear(start, earliest) < 0 ? earliest : start
    setPeriodFromMonthYear(clampedStart, latest)
  }

  async function loadAvailableMonths() {
    isLoadingMonths.value = true
    monthsError.value = ''
    try {
      const { data } = await transactionsApi.availableMonths()
      availableMonths.value = data
      if (!startYear.value) defaultPeriod(data)
    } catch (err) {
      monthsError.value = err.message
    } finally {
      isLoadingMonths.value = false
    }
  }

  watch([startMonthYear, endMonthYear], ([start, end]) => {
    if (!start || !end || compareMonthYear(start, end) <= 0) return
    const s = splitMonthYear(start)
    endMonth.value = s.month
    endYear.value  = s.year
  })

  return {
    MONTHS,
    availableMonths,
    years,
    startMonth,
    startYear,
    endMonth,
    endYear,
    startMonthYear,
    endMonthYear,
    monthsInRange,
    periodInvalid,
    isLoadingMonths,
    monthsError,
    loadAvailableMonths,
  }
}
