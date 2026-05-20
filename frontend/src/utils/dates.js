/** Converte YYYY-MM (API) para MM/AAAA (exibição brasileira). */
export function fmtMonthYear(monthYear) {
  if (!monthYear) return ''
  const match = /^(\d{4})-(\d{2})$/.exec(String(monthYear))
  if (!match) return monthYear
  return `${match[2]}/${match[1]}`
}
