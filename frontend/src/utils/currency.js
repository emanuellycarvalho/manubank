/**
 * Máscara e parsing de valores em Real (pt-BR).
 */

/**
 * Converte número ou string mascarada em float.
 *
 * @param {string|number|null|undefined} raw
 * @returns {number|null}
 */
export function parseBrlToNumber(raw) {
  if (raw === null || raw === undefined || raw === '') return null
  if (typeof raw === 'number') return Number.isFinite(raw) ? raw : null

  let clean = String(raw).trim().replace(/[R$\s]/g, '')
  if (clean === '') return null

  if (clean.includes(',')) {
    clean = clean.replace(/\./g, '').replace(',', '.')
  } else {
    clean = clean.replace(/\./g, '')
  }

  const n = parseFloat(clean)

  return Number.isFinite(n) ? n : null
}

/**
 * Formata número para exibição em input (ex.: R$ 30.000,00).
 *
 * @param {string|number|null|undefined} value
 * @returns {string}
 */
export function formatBrlCurrencyInput(value) {
  const n = parseBrlToNumber(value)
  if (n === null || n <= 0) return ''

  return new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
  }).format(n)
}

/**
 * Aplica máscara BRL enquanto o usuário digita (entrada em centavos).
 *
 * @param {string} input
 * @returns {string}
 */
export function maskBrlCurrencyInput(input) {
  const digits = String(input).replace(/\D/g, '')
  if (digits === '') return ''

  const value = parseInt(digits, 10) / 100

  return new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
  }).format(value)
}
