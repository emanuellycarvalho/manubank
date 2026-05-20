import { ref, computed } from 'vue'

function compareValues(a, b) {
  if (a == null && b == null) return 0
  if (a == null) return -1
  if (b == null) return 1
  if (typeof a === 'number' && typeof b === 'number') return a - b
  return String(a).localeCompare(String(b), 'pt-BR', { numeric: true, sensitivity: 'base' })
}

/**
 * @param {import('vue').Ref<Array>} items
 * @param {Array<{ key: string, getValue: (row: unknown) => unknown }>} columns
 * @param {{ key: string, dir?: 'asc' | 'desc' }} [defaultSort]
 */
export function useTableSort(items, columns, defaultSort = { key: columns[0]?.key, dir: 'asc' }) {
  const sortKey = ref(defaultSort.key)
  const sortDir = ref(defaultSort.dir ?? 'asc')

  function toggleSort(key) {
    if (sortKey.value === key) {
      sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
    } else {
      sortKey.value = key
      sortDir.value = 'asc'
    }
  }

  const sortedItems = computed(() => {
    const col = columns.find((c) => c.key === sortKey.value)
    const list = items.value ?? []
    if (!col) return [...list]

    const mult = sortDir.value === 'asc' ? 1 : -1
    return [...list].sort((a, b) => mult * compareValues(col.getValue(a), col.getValue(b)))
  })

  function sortClass(key) {
    if (sortKey.value !== key) return 'th-sortable--idle'
    return sortDir.value === 'asc' ? 'th-sortable--asc' : 'th-sortable--desc'
  }

  return { sortKey, sortDir, sortedItems, toggleSort, sortClass }
}
