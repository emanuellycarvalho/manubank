/** Formata Date ou ISO parcial para YYYY-MM-DD. */
export function toIsoDate(date) {
  const y = date.getFullYear()
  const m = String(date.getMonth() + 1).padStart(2, '0')
  const d = String(date.getDate()).padStart(2, '0')
  return `${y}-${m}-${d}`
}

/** Intervalo padrão do dashboard: últimos 6 meses até hoje. */
export function defaultDashboardDateRange() {
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  const start = new Date(today.getFullYear(), today.getMonth() - 5, 1)
  return { start: toIsoDate(start), end: toIsoDate(today) }
}

/** Converte YYYY-MM (API) para MM/AAAA (exibição brasileira). */
export function fmtMonthYear(monthYear) {
  if (!monthYear) return ''
  const match = /^(\d{4})-(\d{2})$/.exec(String(monthYear))
  if (!match) return monthYear
  return `${match[2]}/${match[1]}`
}

/** Monta YYYY-MM a partir de ano e mês (1–12 ou "01"–"12"). */
export function toMonthYear(year, month) {
  const y = String(year)
  const m = String(month).padStart(2, '0')
  return `${y}-${m}`
}

/** Compara dois períodos YYYY-MM (-1 | 0 | 1). */
export function compareMonthYear(a, b) {
  if (!a || !b) return 0
  return a < b ? -1 : a > b ? 1 : 0
}

/** Lista cronológica de meses entre início e fim (inclusive). */
export function monthsBetween(start, end) {
  if (!start || !end || compareMonthYear(start, end) > 0) return []

  const out = []
  let [y, m] = start.split('-').map(Number)

  while (true) {
    const key = toMonthYear(y, m)
    out.push(key)
    if (key === end) break
    m += 1
    if (m > 12) {
      m = 1
      y += 1
    }
  }

  return out
}

/** Desloca YYYY-MM em N meses (negativo = passado). */
export function shiftMonthYear(monthYear, delta) {
  const match = /^(\d{4})-(\d{2})$/.exec(monthYear)
  if (!match) return monthYear

  let y = Number(match[1])
  let m = Number(match[2]) + delta

  while (m < 1) {
    m += 12
    y -= 1
  }
  while (m > 12) {
    m -= 12
    y += 1
  }

  return toMonthYear(y, m)
}

/** Último dia do mês YYYY-MM como YYYY-MM-DD. */
export function lastDayOfMonthYear(monthYear) {
  const match = /^(\d{4})-(\d{2})$/.exec(monthYear ?? '')
  if (!match) return ''
  const y = Number(match[1])
  const m = Number(match[2])
  const day = new Date(y, m, 0).getDate()
  return `${match[1]}-${match[2]}-${String(day).padStart(2, '0')}`
}

/** Formata period_label da API para exibição no eixo do gráfico. */
export function fmtPeriodLabel(periodLabel, granularity = 'month') {
  if (!periodLabel) return ''
  if (granularity === 'month' && /^\d{4}-\d{2}$/.test(periodLabel)) {
    return fmtMonthYear(periodLabel)
  }
  if (granularity === 'day' && /^\d{4}-\d{2}-\d{2}$/.test(periodLabel)) {
    const [, y, m, d] = periodLabel.match(/^(\d{4})-(\d{2})-(\d{2})$/)
    return `${d}/${m}/${y}`
  }
  if (granularity === 'week' && /^\d{4}-\d{2}$/.test(periodLabel)) {
    const [, y, w] = periodLabel.match(/^(\d{4})-(\d{2})$/)
    return `Sem. ${w}/${y}`
  }
  if (granularity === 'semester' && /^\d{4}-S[12]$/.test(periodLabel)) {
    const [, y, s] = periodLabel.match(/^(\d{4})-S([12])$/)
    return `${s}º sem. ${y}`
  }
  return periodLabel
}
