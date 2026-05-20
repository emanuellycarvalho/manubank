/**
 * Converte texto para Title Case (cada palavra com inicial maiúscula).
 * Preserva acentos; não altera strings já mistas desnecessariamente.
 */
export function toTitleCase(text) {
  if (text == null || text === '') return ''

  return String(text)
    .trim()
    .split(/\s+/)
    .map((word) => {
      if (!word) return word
      const lower = word.toLocaleLowerCase('pt-BR')
      return lower.charAt(0).toLocaleUpperCase('pt-BR') + lower.slice(1)
    })
    .join(' ')
}

/** Descrição exibida da transação (traduzida ou bruta) em Title Case. */
export function formatTxDescription(tx) {
  const raw = tx?.translated_description || tx?.raw_description || ''
  return toTitleCase(raw)
}
