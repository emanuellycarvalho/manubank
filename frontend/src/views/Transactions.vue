<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { transactionsApi, categoriesApi } from '@/services/api.js'
import RuleModal                  from '@/components/RuleModal.vue'
import ReimbursementClaimModal    from '@/components/ReimbursementClaimModal.vue'
import ReimbursementPaymentModal  from '@/components/ReimbursementPaymentModal.vue'
import ConfirmModal               from '@/components/ConfirmModal.vue'
import ManualTransactionModal     from '@/components/ManualTransactionModal.vue'
import { useTableSort }           from '@/composables/useTableSort.js'
import { fmtMonthYear }           from '@/utils/dates.js'
import { formatTxDescription }    from '@/utils/text.js'
import {
  financePeriodStartDay,
  getFinancePeriodStartDay,
  setFinancePeriodStartDay,
  formatCycleLabel,
} from '@/utils/periodCycle.js'

const TX_SORT_COLS = [
  { key: 'date',        getValue: (t) => t.date },
  { key: 'origin',      getValue: (t) => t.origin },
  { key: 'operation',   getValue: (t) => t.operation },
  { key: 'description', getValue: (t) => formatTxDescription(t) },
  { key: 'category',    getValue: (t) => t.category_name },
  { key: 'amount',      getValue: (t) => t.amount },
]

const TX_HEADERS = [
  { key: 'select',      label: '',          sortable: false },
  { key: 'date',        label: 'Data',      sortable: true },
  { key: 'origin',      label: 'Origem',    sortable: true },
  { key: 'operation',   label: 'Operação',  sortable: true },
  { key: 'description', label: 'Descrição', sortable: true },
  { key: 'category',    label: 'Categoria', sortable: true },
  { key: 'amount',      label: 'Valor',     sortable: true },
  { key: 'actions',     label: 'Ações',     sortable: false },
]

// ── State ──────────────────────────────────────────────────────────────────
const transactions     = ref([])
const availableMonths  = ref([])
const selectedMonth    = ref('')
const isLoading        = ref(false)
const errorMsg         = ref('')
const categories       = ref([])
const searchQuery      = ref('')
const showManualModal  = ref(false)

const PAGE_SIZE_OPTIONS = [
  { value: '25', label: '25' },
  { value: '50', label: '50' },
  { value: '100', label: '100' },
  { value: 'all', label: 'Todos' },
]
const pageSize    = ref('25')
const currentPage = ref(1)

// ── Period config (persisted in localStorage) ──────────────────────────────
const periodStartDay = financePeriodStartDay
const showPeriodConfig = ref(false)

function savePeriodStartDay() {
  setFinancePeriodStartDay(periodStartDay.value)
  showPeriodConfig.value = false
}

function periodLabel(monthYear) {
  return formatCycleLabel(monthYear, periodStartDay.value)
}

// Modal state — only one open at a time
const activeModal      = ref(null)  // 'rule' | 'claim' | 'payment'
const selectedTx       = ref(null)
/** @type {import('vue').Ref<{ ids: number[], detail?: string } | null>} */
const deleteConfirm    = ref(null)
const isDeleting       = ref(false)
const selectedIds      = ref(new Set())
const selectAllRef     = ref(null)

// Toast
const toast = ref(null)  // { message, type }
let toastTimer = null

function showToast(message, type = 'success') {
  clearTimeout(toastTimer)
  toast.value = { message, type }
  toastTimer  = setTimeout(() => { toast.value = null }, 4000)
}

// ── Load ───────────────────────────────────────────────────────────────────
async function loadMonths() {
  try {
    const { data } = await transactionsApi.availableMonths()
    availableMonths.value = data
    if (!selectedMonth.value && data.length) selectedMonth.value = data[0]
  } catch (err) {
    errorMsg.value = err.message
  }
}

async function loadTransactions() {
  isLoading.value = true
  errorMsg.value  = ''
  try {
    const { data } = await transactionsApi.list(selectedMonth.value)
    transactions.value = data
  } catch (err) {
    errorMsg.value = err.message
  } finally {
    isLoading.value = false
  }
}

async function onMonthChange() {
  await loadTransactions()
}

onMounted(async () => {
  await loadMonths()
  await loadTransactions()
  try {
    const { data } = await categoriesApi.list()
    categories.value = data
  } catch { /* não bloqueia */ }
})

// ── Computed ───────────────────────────────────────────────────────────────
const totalEntrada = computed(() =>
  transactions.value
    .filter(t => t.type === 'entrada')
    .reduce((s, t) => s + t.amount, 0)
)

const totalSaida = computed(() =>
  transactions.value
    .filter(t => t.type === 'saída')
    .reduce((s, t) => s + t.amount, 0)
)

const filteredTransactions = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  if (!q) return transactions.value

  return transactions.value.filter((tx) => {
    const haystack = [
      formatTxDescription(tx),
      tx.raw_description,
      tx.translated_description,
      tx.origin,
      tx.operation,
      tx.category_name,
      tx.type,
      fmtDate(tx.date),
      fmt(tx.amount),
    ]
      .filter(Boolean)
      .join(' ')
      .toLowerCase()

    return haystack.includes(q)
  })
})

const { sortedItems: sortedTransactions, toggleSort, sortClass } = useTableSort(
  filteredTransactions,
  TX_SORT_COLS,
  { key: 'date', dir: 'desc' },
)

const hasSearchFilter = computed(() => searchQuery.value.trim().length > 0)

const totalFiltered = computed(() => sortedTransactions.value.length)

const effectivePageSize = computed(() => {
  if (pageSize.value === 'all') return Math.max(totalFiltered.value, 1)
  return parseInt(pageSize.value, 10)
})

const totalPages = computed(() => {
  const total = totalFiltered.value
  if (!total || pageSize.value === 'all') return 1
  return Math.ceil(total / effectivePageSize.value)
})

const paginatedTransactions = computed(() => {
  const items = sortedTransactions.value
  if (pageSize.value === 'all') return items
  const start = (currentPage.value - 1) * effectivePageSize.value
  return items.slice(start, start + effectivePageSize.value)
})

const rangeStart = computed(() => {
  if (!totalFiltered.value) return 0
  if (pageSize.value === 'all') return 1
  return (currentPage.value - 1) * effectivePageSize.value + 1
})

const rangeEnd = computed(() => {
  if (!totalFiltered.value) return 0
  if (pageSize.value === 'all') return totalFiltered.value
  return Math.min(currentPage.value * effectivePageSize.value, totalFiltered.value)
})

const showPageNav = computed(() => pageSize.value !== 'all' && totalPages.value > 1)

const selectableIds = computed(() => sortedTransactions.value.map((t) => t.id))

const selectedCount = computed(() => selectedIds.value.size)

const allFilteredSelected = computed(() => {
  const ids = selectableIds.value
  return ids.length > 0 && ids.every((id) => selectedIds.value.has(id))
})

const someFilteredSelected = computed(() =>
  selectableIds.value.some((id) => selectedIds.value.has(id)),
)

const selectionIndeterminate = computed(
  () => someFilteredSelected.value && !allFilteredSelected.value,
)

function goToPage(page) {
  currentPage.value = Math.max(1, Math.min(page, totalPages.value))
}

watch([searchQuery, selectedMonth, pageSize], () => {
  currentPage.value = 1
})

watch(selectedMonth, () => {
  selectedIds.value = new Set()
})

watch(
  [selectionIndeterminate, allFilteredSelected, () => sortedTransactions.value.length],
  ([indeterminate]) => {
    if (selectAllRef.value) selectAllRef.value.indeterminate = indeterminate
  },
  { flush: 'post' },
)

watch(transactions, () => {
  const valid = new Set(transactions.value.map((t) => t.id))
  const pruned = [...selectedIds.value].filter((id) => valid.has(id))
  if (pruned.length !== selectedIds.value.size) {
    selectedIds.value = new Set(pruned)
  }
})

watch(totalPages, (tp) => {
  if (currentPage.value > tp) currentPage.value = tp
})

// ── Formatting ─────────────────────────────────────────────────────────────
function fmt(amount) {
  return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(amount)
}

function fmtDate(dateStr) {
  if (!dateStr) return '—'
  const [y, m, d] = dateStr.split('-')
  if (!d) return dateStr
  return `${d}/${m}/${y}`
}

function isSaida(type) {
  return type === 'saída'
}

function amountClass(type) {
  return isSaida(type) ? 'amount--out' : 'amount--in'
}

function rowClass(type) {
  return isSaida(type) ? 'row--out' : 'row--in'
}

// ── Inline category change ──────────────────────────────────────────────────
async function changeCategory(tx, newCategoryId) {
  const prev = tx.category_id
  const cat  = categories.value.find(c => c.id === newCategoryId)
  if (!cat || cat.id === prev) return

  // Optimistic update
  tx.category_id    = cat.id
  tx.category_name  = cat.name
  tx.category_color = cat.color

  try {
    await transactionsApi.updateCategory(tx.id, newCategoryId)
  } catch (err) {
    // Revert on failure
    const old = categories.value.find(c => c.id === prev)
    if (old) { tx.category_id = old.id; tx.category_name = old.name; tx.category_color = old.color }
    showToast('Erro ao alterar categoria: ' + err.message, 'error')
  }
}

// ── Resizable columns ──────────────────────────────────────────────────────
const COL_WIDTHS_KEY = 'finance_col_widths'

const DEFAULT_WIDTHS = [44, 90, 120, 130, 260, 180, 140, 210]

function loadWidths() {
  try {
    const saved = JSON.parse(localStorage.getItem(COL_WIDTHS_KEY))
    if (Array.isArray(saved) && saved.length === DEFAULT_WIDTHS.length) return saved
  } catch { /* ignore */ }
  return [...DEFAULT_WIDTHS]
}

const colWidths    = ref(loadWidths())
const isResizing   = ref(false)

function startResize(colIndex, event) {
  event.preventDefault()
  isResizing.value  = true
  const startX      = event.clientX
  const startWidth  = colWidths.value[colIndex]

  function onMove(e) {
    colWidths.value[colIndex] = Math.max(60, startWidth + (e.clientX - startX))
  }

  function onUp() {
    isResizing.value = false
    localStorage.setItem(COL_WIDTHS_KEY, JSON.stringify(colWidths.value))
    document.removeEventListener('mousemove', onMove)
    document.removeEventListener('mouseup', onUp)
  }

  document.addEventListener('mousemove', onMove)
  document.addEventListener('mouseup', onUp)
}

// ── Modals ─────────────────────────────────────────────────────────────────
function openRuleModal(tx) {
  selectedTx.value  = tx
  activeModal.value = 'rule'
}

function openClaimModal(tx) {
  selectedTx.value  = tx
  activeModal.value = 'claim'
}

function openPaymentModal(tx) {
  selectedTx.value  = tx
  activeModal.value = 'payment'
}

function closeModal() {
  activeModal.value = null
  selectedTx.value  = null
}

async function onRuleCreated(data) {
  closeModal()
  await loadTransactions()
  const n       = data?.transactions_updated ?? 0
  const existed = data?.already_existed ?? false
  const suffix  = n > 0
    ? ` ${n} transaç${n === 1 ? 'ão atualizada' : 'ões atualizadas'}.`
    : ' Será aplicada nas próximas importações.'
  showToast(existed
    ? `Regra já existia — aplicada retroativamente.${suffix}`
    : `Regra criada!${suffix}`
  )
}

async function onClaimCreated() {
  closeModal()
}

async function onPaymentRegistered() {
  closeModal()
}

function isSelected(id) {
  return selectedIds.value.has(id)
}

function toggleSelect(id) {
  const next = new Set(selectedIds.value)
  if (next.has(id)) next.delete(id)
  else next.add(id)
  selectedIds.value = next
}

function toggleSelectAll() {
  if (allFilteredSelected.value) {
    selectedIds.value = new Set()
    return
  }
  selectedIds.value = new Set(selectableIds.value)
}

function clearSelection() {
  selectedIds.value = new Set()
}

function requestDelete(tx) {
  deleteConfirm.value = {
    ids: [tx.id],
    detail: tx.translated_description || tx.raw_description,
  }
}

function requestBulkDelete() {
  if (!selectedCount.value) return
  deleteConfirm.value = { ids: [...selectedIds.value] }
}

function cancelDelete() {
  if (!isDeleting.value) deleteConfirm.value = null
}

const deleteConfirmTitle = computed(() => {
  const n = deleteConfirm.value?.ids.length ?? 0
  return n <= 1 ? 'Excluir transação?' : `Excluir ${n} transações?`
})

async function onManualTransactionSaved(tx) {
  showManualModal.value = false
  const month = tx?.month_year
  if (month && !availableMonths.value.includes(month)) {
    availableMonths.value = [month, ...availableMonths.value].sort().reverse()
  }
  if (month && selectedMonth.value && selectedMonth.value !== month) {
    selectedMonth.value = month
  }
  await loadTransactions()
  showToast('Transação adicionada.')
}

async function confirmDelete() {
  const ids = deleteConfirm.value?.ids
  if (!ids?.length) return

  isDeleting.value = true
  const idSet = new Set(ids)

  try {
    const results = await Promise.allSettled(ids.map((id) => transactionsApi.remove(id)))
    const failed = results.filter((r) => r.status === 'rejected').length
    const ok = ids.length - failed

    transactions.value = transactions.value.filter((t) => !idSet.has(t.id))
    selectedIds.value = new Set([...selectedIds.value].filter((id) => !idSet.has(id)))
    deleteConfirm.value = null

    if (failed) {
      showToast(`${ok} excluída(s), ${failed} falharam.`, 'error')
    } else {
      showToast(ok === 1 ? 'Transação excluída.' : `${ok} transações excluídas.`)
    }
  } catch (err) {
    showToast('Erro ao excluir: ' + err.message, 'error')
  } finally {
    isDeleting.value = false
  }
}
</script>

<template>
  <div class="page">
    <!-- Header -->
    <header class="page-header">
      <div>
        <h2 class="page-title">Extrato</h2>
        <p class="page-subtitle">Histórico de transações importadas</p>
      </div>

      <div class="header-actions">
        <div class="filter-group">
        <div class="filter-label-row">
          <label class="filter-label" for="month-select">Período</label>
          <button class="btn-period-config" title="Configurar dia de início" @click="showPeriodConfig = !showPeriodConfig">
            <unicon name="setting" width="16" height="16" />
          </button>
        </div>

        <!-- Period config popover -->
        <div v-if="showPeriodConfig" class="period-config">
          <span class="period-config__label">Início do ciclo: dia</span>
          <input
            v-model.number="periodStartDay"
            type="number" min="1" max="28"
            class="period-config__input"
            @keyup.enter="savePeriodStartDay"
          />
          <button class="period-config__btn" @click="savePeriodStartDay">OK</button>
        </div>

        <select
          id="month-select"
          v-model="selectedMonth"
          class="form-control"
          @change="onMonthChange"
        >
          <option value="">Todos os meses</option>
          <option v-for="m in availableMonths" :key="m" :value="m">
            {{ fmtMonthYear(m) }}
          </option>
        </select>
        <span v-if="selectedMonth" class="period-range">
          {{ periodLabel(selectedMonth) }}
        </span>
        </div>
      </div>
    </header>

    <!-- Toast -->
    <Transition name="toast">
      <div v-if="toast" class="toast" :class="`toast--${toast.type}`">
        {{ toast.message }}
      </div>
    </Transition>

    <!-- Error -->
    <div v-if="errorMsg" class="alert alert--error">
      <unicon name="times-circle" width="14" height="14" />
      {{ errorMsg }}
    </div>

    <!-- Summary cards -->
    <div v-if="transactions.length" class="summary-row">
      <div class="summary-card summary-card--in">
        <span class="summary-card__label">Entradas</span>
        <span class="summary-card__value">{{ fmt(totalEntrada) }}</span>
      </div>
      <div class="summary-card summary-card--out">
        <span class="summary-card__label">Saídas</span>
        <span class="summary-card__value">{{ fmt(totalSaida) }}</span>
      </div>
      <div class="summary-card summary-card--balance">
        <span class="summary-card__label">Saldo</span>
        <span
          class="summary-card__value"
          :class="totalEntrada - totalSaida >= 0 ? 'text-green' : 'text-red'"
        >
          {{ fmt(totalEntrada - totalSaida) }}
        </span>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="isLoading" class="loading-state">
      <span class="spinner" aria-hidden="true"></span>
      Carregando transações…
    </div>

    <template v-else>
      <div class="table-toolbar">
        <label v-if="transactions.length" class="search-field">
          <span class="search-field__label">Buscar</span>
          <input
            v-model="searchQuery"
            type="search"
            class="form-control search-field__input"
            placeholder="Descrição, categoria, origem, valor…"
            autocomplete="off"
          />
        </label>
        <p v-if="hasSearchFilter" class="search-hint">
          {{ sortedTransactions.length }} de {{ transactions.length }} transações
        </p>
        <div v-if="selectedCount" class="selection-bar">
          <span class="selection-bar__count">
            {{ selectedCount }} selecionada{{ selectedCount === 1 ? '' : 's' }}
          </span>
          <button type="button" class="btn btn--outline btn--sm" @click="clearSelection">
            Limpar seleção
          </button>
          <button
            type="button"
            class="btn btn--danger btn--sm"
            @click="requestBulkDelete"
          >
            <unicon name="trash-alt" width="14" height="14" />
            Excluir selecionadas
          </button>
        </div>
        <button type="button" class="btn btn--primary" @click="showManualModal = true">
          <unicon name="plus" width="16" height="16" />
          Nova transação
        </button>
      </div>

      <div v-if="!transactions.length" class="empty-state">
        <unicon name="file-alt" width="48" height="48" />
        <p>Nenhuma transação encontrada. <RouterLink to="/importar">Importe um extrato</RouterLink> ou adicione manualmente.</p>
      </div>

      <div v-else-if="!sortedTransactions.length" class="empty-state empty-state--compact">
        <p>Nenhuma transação corresponde à busca.</p>
      </div>

      <div v-else class="card table-wrapper" :class="{ 'is-resizing': isResizing }">
      <table class="table">
        <colgroup>
          <col v-for="(w, i) in colWidths" :key="i" :style="{ width: w + 'px' }" />
        </colgroup>
        <thead>
          <tr>
            <th
              v-for="(col, i) in TX_HEADERS"
              :key="col.key"
              :class="[
                col.sortable && 'th-sortable',
                col.sortable && sortClass(col.key),
                col.key === 'select' && 'col-select',
                col.key === 'actions' && 'col-actions',
              ]"
              @click="col.sortable && toggleSort(col.key)"
            >
              <label v-if="col.key === 'select'" class="select-all-label" @click.stop>
                <input
                  ref="selectAllRef"
                  type="checkbox"
                  class="tx-checkbox"
                  :checked="allFilteredSelected"
                  :disabled="!selectableIds.length"
                  aria-label="Selecionar todas as transações visíveis"
                  @change="toggleSelectAll"
                />
              </label>
              <template v-else>{{ col.label }}</template>
              <div
                v-if="col.key !== 'select' && col.key !== 'actions'"
                class="resize-handle"
                @mousedown.stop="startResize(i, $event)"
              ></div>
            </th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="tx in paginatedTransactions"
            :key="tx.id"
            :class="[rowClass(tx.type), isSelected(tx.id) && 'row--selected']"
          >
            <td class="col-select">
              <input
                type="checkbox"
                class="tx-checkbox"
                :checked="isSelected(tx.id)"
                :aria-label="`Selecionar ${formatTxDescription(tx)}`"
                @change="toggleSelect(tx.id)"
              />
            </td>
            <td class="col-date">{{ fmtDate(tx.date) }}</td>
            <td class="td-clip">{{ tx.origin }}</td>
            <td class="td-clip">{{ tx.operation }}</td>
            <td>
              <span class="desc-main">{{ formatTxDescription(tx) }}</span>
              <span
                v-if="tx.installment_current && tx.installment_total"
                class="installment-badge"
              >
                {{ tx.installment_current }}/{{ tx.installment_total }}
              </span>
            </td>
            <td>
              <div class="category-cell">
                <span class="color-dot" :style="{ background: tx.category_color }"></span>
                <select
                  class="category-select"
                  :value="tx.category_id"
                  @change="changeCategory(tx, Number($event.target.value))"
                >
                  <option
                    v-for="cat in categories"
                    :key="cat.id"
                    :value="cat.id"
                  >{{ cat.name }}</option>
                </select>
              </div>
            </td>
            <td class="col-amount">
              <span :class="amountClass(tx.type)">
                {{ fmt(tx.amount) }}
              </span>
            </td>
            <td class="col-actions">
              <button
                v-if="tx.type === 'saída'"
                class="btn-action btn-action--claim"
                title="Gerar pendência de reembolso"
                @click="openClaimModal(tx)"
              >
                <unicon name="clipboard-alt" width="14" height="14" />
                Pendência
              </button>

              <button
                v-if="tx.type === 'entrada'"
                class="btn-action btn-action--payment"
                title="Vincular recebimento a pendências"
                @click="openPaymentModal(tx)"
              >
                <unicon name="link" width="14" height="14" />
                Reembolso
              </button>

              <button
                class="btn-action btn-action--delete"
                title="Excluir transação"
                @click="requestDelete(tx)"
              >
                <unicon name="trash-alt" width="14" height="14" />
                Excluir
              </button>

              <span
                v-if="tx.category_name === 'Outros'"
                class="action-tooltip"
              >
                <button
                  type="button"
                  class="btn-action btn-action--rule"
                  aria-label="Criar regra de categorização"
                  @click="openRuleModal(tx)"
                >
                  <unicon name="tag-alt" width="14" height="14" />
                  Regra
                </button>
                <span class="action-tooltip__bubble" role="tooltip">
                  Cria uma regra a partir desta linha: descrições parecidas nas próximas importações
                  passam a ser categorizadas automaticamente. Pode atualizar transações já importadas.
                </span>
              </span>
            </td>
          </tr>
        </tbody>
      </table>

      <div class="table-pagination">
        <div class="table-pagination__size">
          <label for="tx-page-size" class="table-pagination__label">Exibir</label>
          <select
            id="tx-page-size"
            v-model="pageSize"
            class="form-control table-pagination__select"
          >
            <option
              v-for="opt in PAGE_SIZE_OPTIONS"
              :key="opt.value"
              :value="opt.value"
            >
              {{ opt.label }}
            </option>
          </select>
          <span class="table-pagination__label">por página</span>
        </div>

        <p class="table-pagination__info">
          <template v-if="pageSize === 'all'">
            Mostrando todos os {{ totalFiltered }} registros
          </template>
          <template v-else>
            {{ rangeStart }}–{{ rangeEnd }} de {{ totalFiltered }} registros
          </template>
        </p>

        <nav v-if="showPageNav" class="table-pagination__nav" aria-label="Paginação do extrato">
          <button
            type="button"
            class="btn-page"
            :disabled="currentPage <= 1"
            aria-label="Página anterior"
            @click="goToPage(currentPage - 1)"
          >
            <unicon name="angle-left-b" width="18" height="18" />
          </button>
          <span class="table-pagination__pages">
            Página {{ currentPage }} de {{ totalPages }}
          </span>
          <button
            type="button"
            class="btn-page"
            :disabled="currentPage >= totalPages"
            aria-label="Próxima página"
            @click="goToPage(currentPage + 1)"
          >
            <unicon name="angle-right-b" width="18" height="18" />
          </button>
        </nav>
      </div>
      </div>
    </template>

    <!-- ── Modais ── -->
    <ManualTransactionModal
      v-if="showManualModal"
      @close="showManualModal = false"
      @saved="onManualTransactionSaved"
    />

    <RuleModal
      v-if="activeModal === 'rule' && selectedTx"
      :transaction="selectedTx"
      @close="closeModal"
      @saved="onRuleCreated"
    />

    <ReimbursementClaimModal
      v-if="activeModal === 'claim' && selectedTx"
      :transaction="selectedTx"
      @close="closeModal"
      @saved="onClaimCreated"
    />

    <ReimbursementPaymentModal
      v-if="activeModal === 'payment' && selectedTx"
      :transaction="selectedTx"
      @close="closeModal"
      @saved="onPaymentRegistered"
    />

    <ConfirmModal
      v-if="deleteConfirm"
      :title="deleteConfirmTitle"
      message="Esta ação não pode ser desfeita."
      :detail="deleteConfirm.detail"
      confirm-label="Excluir"
      cancel-label="Cancelar"
      :loading="isDeleting"
      @close="cancelDelete"
      @confirm="confirmDelete"
    />
  </div>
</template>

<style scoped>
.page {
  width: 100%;
  color: #e7e7e7;
}

.page-header {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  margin-bottom: 24px;
  gap: 16px;
  flex-wrap: wrap;
}

/* page-title/subtitle from global style.css */

.header-actions {
  display: flex;
  align-items: flex-end;
  gap: 16px;
  flex-wrap: wrap;
}

.btn.btn--primary {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 9px 16px;
  border: none;
  border-radius: var(--radius-sm);
  font-size: 0.88rem;
  font-weight: 600;
  cursor: pointer;
  font-family: inherit;
  background: var(--color-accent);
  color: var(--color-on-accent);
  white-space: nowrap;
}

.btn.btn--primary:hover { filter: brightness(1.08); }

.table-toolbar {
  display: flex;
  align-items: flex-end;
  gap: 16px;
  margin-bottom: 12px;
  flex-wrap: wrap;
}

.table-toolbar .btn--primary {
  margin-left: auto;
  flex-shrink: 0;
}

.selection-bar {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
  padding: 8px 12px;
  background: rgba(197, 119, 0, 0.12);
  border: 1px solid var(--color-accent);
  border-radius: var(--radius-sm);
}

.selection-bar__count {
  font-size: 0.88rem;
  font-weight: 600;
  color: var(--color-text);
  white-space: nowrap;
}

.btn--outline {
  background: transparent;
  border: 1px solid var(--color-border-light);
  color: var(--color-text-muted);
  padding: 7px 12px;
  border-radius: var(--radius-sm);
  font-size: 0.82rem;
  font-weight: 600;
  cursor: pointer;
  font-family: inherit;
}

.btn--outline:hover {
  border-color: var(--color-accent);
  color: var(--color-text);
}

.btn--danger {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: var(--color-error);
  color: #e7e7e7;
  border: none;
  padding: 7px 14px;
  border-radius: var(--radius-sm);
  font-size: 0.82rem;
  font-weight: 600;
  cursor: pointer;
  font-family: inherit;
}

.btn--danger:hover { filter: brightness(1.1); }

.btn--sm { padding: 6px 12px; font-size: 0.8rem; }

.col-select {
  width: 44px;
  text-align: center;
  padding-left: 10px;
  padding-right: 10px;
  overflow: visible;
}

.select-all-label {
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0;
  cursor: pointer;
}

.tx-checkbox {
  width: 16px;
  height: 16px;
  accent-color: var(--color-accent);
  cursor: pointer;
}

.row--selected {
  background: rgba(197, 119, 0, 0.1) !important;
}

.row--selected:hover {
  background: rgba(197, 119, 0, 0.16) !important;
}

.search-field {
  display: flex;
  flex-direction: column;
  gap: 4px;
  flex: 1;
  min-width: 200px;
  max-width: none;
}

.search-field__label {
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--color-text-muted);
}

.search-field__input {
  width: 100%;
}

.search-hint {
  margin: 0;
  font-size: 0.82rem;
  color: var(--color-text-muted);
  align-self: center;
}

.empty-state--compact {
  padding: 32px 20px;
  text-align: center;
  color: var(--color-text-muted);
}

/* ── Filter ── */
.filter-group { display: flex; flex-direction: column; gap: 4px; min-width: 200px; }

.filter-label-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.filter-label { font-size: .8rem; font-weight: 600; color: var(--color-text-muted); }

.btn-period-config {
  display: inline-flex;
  align-items: center;
  background: none;
  border: none;
  cursor: pointer;
  padding: 2px;
  opacity: .75;
  transition: opacity .15s;
}
.btn-period-config:hover { opacity: 1; }

.period-config {
  display: flex;
  align-items: center;
  gap: 6px;
  background: var(--color-bg-elevated);
  border: 1px solid var(--color-border-light);
  border-radius: var(--radius-sm);
  padding: 6px 10px;
  font-size: .8rem;
  color: var(--color-text-muted);
}
.period-config__label { white-space: nowrap; }
.period-config__input {
  width: 48px;
  border: 1px solid var(--color-border-light);
  border-radius: var(--radius-sm);
  padding: 3px 6px;
  font-size: .82rem;
  text-align: center;
  background: var(--color-bg-input);
  color: var(--color-text);
}
.period-config__btn {
  background: var(--color-accent);
  color: var(--color-bg-primary);
  border: none;
  border-radius: var(--radius-sm);
  padding: 3px 8px;
  font-size: .78rem;
  font-weight: 600;
  cursor: pointer;
}

.period-range {
  font-size: .78rem;
  color: #e7e7e7;
  font-weight: 500;
  letter-spacing: .2px;
}

/* ── Summary cards ── */
.summary-row {
  display: flex;
  gap: 16px;
  margin-bottom: 20px;
  flex-wrap: wrap;
}

.summary-card {
  flex: 1;
  min-width: 140px;
  padding: 16px 20px;
  border-radius: 10px;
  display: flex;
  flex-direction: column;
  gap: 4px;
  box-shadow: 2px 4px rgb(0 0 0 / 35%);
  border: 1px solid #000614;
}

.summary-card--in {
  background: var(--color-success);
}
.summary-card--out {
  background: var(--color-error);
}
.summary-card--balance {
  background: var(--color-info);
}

.summary-card__label {
  font-size: .75rem;
  font-weight: 600;
  color: var(--color-text-muted);
  text-transform: uppercase;
  letter-spacing: .5px;
}

.summary-card__value {
  font-size: 1.25rem;
  font-weight: 700;
  color: #e7e7e7;
}

.text-green { color: var(--color-success-text); }
.text-red   { color: var(--color-error-text); }

.table-wrapper {
  overflow-x: auto;
  border: none;
  box-shadow: none;
}

/* Disable text selection while dragging a resize handle */
.is-resizing { user-select: none; cursor: col-resize; }

.table {
  width: 100%;
  table-layout: fixed;
  border-collapse: collapse;
  font-size: .86rem;
}

.table th,
.table td {
  padding: 10px 14px;
  text-align: left;
  border: none;
  border-bottom: 1px solid var(--color-border-subtle-dark);
  border-left: none;
  border-right: none;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  color: #e7e7e7;
}

.table th {
  position: relative;
  background: var(--color-bg-secondary);
  font-weight: 600;
  color: #e7e7e7;
  font-size: .78rem;
  text-transform: uppercase;
  letter-spacing: .5px;
  user-select: none;
}

.table tbody tr:last-child td { border-bottom: none; }
.table tbody tr:hover { background: rgba(255, 255, 255, 0.03); }

.row--in  { border-left: 3px solid var(--color-success); }
.row--out { border-left: 3px solid var(--color-error); }

/* Resize handle — right edge of each <th> */
.resize-handle {
  position: absolute;
  top: 0;
  right: 0;
  width: 6px;
  height: 100%;
  cursor: col-resize;
  z-index: 1;
}

.resize-handle::after {
  content: none;
}

.col-date  { color: #e7e7e7; }
.col-amount { text-align: right; font-weight: 600; }
.col-actions {
  overflow: visible;
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  align-items: center;
}

/* Clipped text cells */
.td-clip { overflow: hidden; text-overflow: ellipsis; }

.desc-main { display: block; overflow: hidden; text-overflow: ellipsis; }

.installment-badge {
  display: inline-block;
  font-size: .72rem;
  background: rgba(0, 49, 163, 0.4);
  color: var(--color-text);
  border-radius: 999px;
  padding: 1px 7px;
  margin-top: 3px;
}

.category-cell { display: flex; align-items: center; gap: 6px; }

.category-select {
  border: none;
  background: transparent;
  font-size: .84rem;
  color: #e7e7e7;
  cursor: pointer;
  padding: 2px 4px;
  border-radius: 5px;
  outline: none;
  max-width: 130px;
  transition: background .15s;
}
.category-select:hover  { background: var(--color-bg-elevated); }
.category-select:focus  { background: var(--color-bg-elevated); outline: 2px solid var(--color-accent); }
.category-select option { background: var(--color-bg-secondary); color: var(--color-text); }

.color-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  flex-shrink: 0;
}

.amount--in  { color: var(--color-success-text); }
.amount--out { color: var(--color-error-text); }

/* ── Action buttons ── */
.btn-action {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 10px;
  border: none;
  border-radius: 6px;
  font-size: .75rem;
  font-weight: 600;
  cursor: pointer;
  white-space: nowrap;
  transition: filter .15s;
}

.btn-action:hover { filter: brightness(.9); }

.btn-action--rule    { background: var(--color-info); color: #e7e7e7; }

.action-tooltip {
  position: relative;
  display: inline-flex;
}

.action-tooltip__bubble {
  position: absolute;
  z-index: 30;
  bottom: calc(100% + 8px);
  left: 50%;
  transform: translateX(-50%);
  width: max-content;
  max-width: 260px;
  padding: 8px 10px;
  border-radius: var(--radius-sm);
  background: var(--color-bg-elevated);
  border: 1px solid var(--color-border-light);
  box-shadow: var(--shadow-md);
  font-size: 0.72rem;
  font-weight: 400;
  line-height: 1.4;
  color: var(--color-text);
  text-align: left;
  white-space: normal;
  opacity: 0;
  visibility: hidden;
  pointer-events: none;
  transition: opacity 0.15s ease, visibility 0.15s ease;
}

.action-tooltip__bubble::after {
  content: '';
  position: absolute;
  top: 100%;
  left: 50%;
  transform: translateX(-50%);
  border: 5px solid transparent;
  border-top-color: var(--color-border-light);
}

.action-tooltip:hover .action-tooltip__bubble,
.action-tooltip:focus-within .action-tooltip__bubble {
  opacity: 1;
  visibility: visible;
}
.btn-action--claim   { background: var(--color-accent);}
.btn-action--payment { background: var(--color-success); color: #e7e7e7; }
.btn-action--delete  { background: var(--color-error); color: #e7e7e7; }

/* ── Pagination ── */
.table-pagination {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 12px 20px;
  padding: 12px 16px;
  border-top: 1px solid var(--color-border-dark);
  background: var(--color-bg-secondary);
}

.table-pagination__size {
  display: flex;
  align-items: center;
  gap: 8px;
}

.table-pagination__label {
  font-size: 0.82rem;
  color: var(--color-text-muted);
  white-space: nowrap;
}

.table-pagination__select {
  width: auto;
  min-width: 72px;
  padding: 6px 28px 6px 10px;
  font-size: 0.85rem;
}

.table-pagination__info {
  font-size: 0.85rem;
  color: var(--color-text-muted);
  margin: 0;
}

.table-pagination__nav {
  display: flex;
  align-items: center;
  gap: 10px;
}

.table-pagination__pages {
  font-size: 0.85rem;
  color: var(--color-text);
  min-width: 7rem;
  text-align: center;
}

.btn-page {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 34px;
  height: 34px;
  padding: 0;
  border: 1px solid var(--color-border-light);
  border-radius: var(--radius-sm);
  background: var(--color-bg-elevated);
  color: var(--color-text);
  cursor: pointer;
  transition: background 0.15s, border-color 0.15s, opacity 0.15s;
}

.btn-page:hover:not(:disabled) {
  background: var(--color-info);
  border-color: var(--color-border-light);
}

.btn-page:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.spinner {
  display: inline-block;
  width: 16px;
  height: 16px;
  border: 2px solid var(--color-border-light);
  border-top-color: var(--color-accent);
  border-radius: 50%;
  animation: spin .7s linear infinite;
}

@keyframes spin { to { transform: rotate(360deg); } }

/* ── Toast ── */
.toast {
  position: fixed;
  bottom: 28px;
  left: 50%;
  transform: translateX(-50%);
  padding: 12px 22px;
  border-radius: 10px;
  font-size: .9rem;
  font-weight: 500;
  color: var(--color-text);
  box-shadow: var(--shadow-md);
  z-index: 1000;
  white-space: nowrap;
}

.toast--success { background: var(--color-success); color: var(--color-text); border: none; }
.toast--error   { background: var(--color-error); color: var(--color-text); border: none; }

.toast-enter-active, .toast-leave-active { transition: opacity .3s, transform .3s; }
.toast-enter-from { opacity: 0; transform: translateX(-50%) translateY(12px); }
.toast-leave-to   { opacity: 0; transform: translateX(-50%) translateY(12px); }
</style>
