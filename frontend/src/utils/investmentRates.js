/** Converte taxa decimal (0.144) para percentual de exibição (14.4). */
export function rateToPercent(decimal) {
  if (decimal == null || Number.isNaN(decimal)) return ''
  return round4(decimal * 100)
}

/** Converte percentual (14.4) para taxa decimal (0.144). */
export function percentToRate(percent) {
  const n = parseFloat(String(percent).replace(',', '.'))
  if (Number.isNaN(n)) return null
  return round6(n / 100)
}

export function round4(n) {
  return Math.round(n * 10000) / 10000
}

export function round6(n) {
  return Math.round(n * 1000000) / 1000000
}

/** yearly = baseCdi * (cdi% / 100) — entradas em %. */
export function yearlyFromCdi(baseCdiYearly, cdiPercentage) {
  const base = Number(baseCdiYearly)
  const cdi = parseFloat(String(cdiPercentage).replace(',', '.'))
  if (Number.isNaN(base) || Number.isNaN(cdi)) return null
  return round6((base / 100) * (cdi / 100))
}

export function monthlyFromYearly(yearlyDecimal) {
  const y = Number(yearlyDecimal)
  if (Number.isNaN(y) || y <= -1) return null
  return round6(Math.pow(1 + y, 1 / 12) - 1)
}

export function yearlyFromMonthly(monthlyDecimal) {
  const m = Number(monthlyDecimal)
  if (Number.isNaN(m) || m <= -1) return null
  return round6(Math.pow(1 + m, 12) - 1)
}

export function cdiFromYearly(baseCdiYearly, yearlyDecimal) {
  const base = Number(baseCdiYearly)
  const y = Number(yearlyDecimal)
  if (Number.isNaN(base) || base <= 0 || Number.isNaN(y)) return null
  return round4((y / (base / 100)) * 100)
}
