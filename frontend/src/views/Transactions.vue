<script setup>
import { ref, computed, onMounted } from 'vue'
import { transactionsApi, categoriesApi } from '@/services/api.js'
import RuleModal                  from '@/components/RuleModal.vue'
import ReimbursementClaimModal    from '@/components/ReimbursementClaimModal.vue'
import ReimbursementPaymentModal  from '@/components/ReimbursementPaymentModal.vue'
import ConfirmModal               from '@/components/ConfirmModal.vue'

// ── State ──────────────────────────────────────────────────────────────────
const transactions     = ref([])
const availableMonths  = ref([])
const selectedMonth    = ref('')
const isLoading        = ref(false)
const errorMsg         = ref('')
const categories       = ref([])

// ── Period config (persisted in localStorage) ──────────────────────────────
const STORAGE_KEY = 'finance_period_start_day'
const periodStartDay = ref(parseInt(localStorage.getItem(STORAGE_KEY) ?? '29', 10))
const showPeriodConfig = ref(false)

function savePeriodStartDay() {
  const day = Math.min(28, Math.max(1, periodStartDay.value))
  periodStartDay.value = day
  localStorage.setItem(STORAGE_KEY, String(day))
  showPeriodConfig.value = false
}

/** Returns last business day on or before the given date */
function lastBusinessDay(year, month, day) {
  const daysInMonth = new Date(year, month, 0).getDate()
  const d = Math.min(day, daysInMonth)
  const dt = new Date(year, month - 1, d)
  const dow = dt.getDay() // 0=Sun, 6=Sat
  if (dow === 6) dt.setDate(dt.getDate() - 1)  // Sat → Fri
  if (dow === 0) dt.setDate(dt.getDate() - 2)  // Sun → Fri
  return dt
}

function fmtShort(dt) {
  return `${String(dt.getDate()).padStart(2,'0')}/${String(dt.getMonth()+1).padStart(2,'0')}`
}

function periodLabel(monthYear) {
  if (!monthYear) return ''
  const [y, m] = monthYear.split('-').map(Number)
  const start = lastBusinessDay(y, m - 1, periodStartDay.value)  // prev month
  const end   = lastBusinessDay(y, m,     periodStartDay.value - 1) // this month, day before
  return `${fmtShort(start)} – ${fmtShort(end)}`
}

// Modal state — only one open at a time
const activeModal      = ref(null)  // 'rule' | 'claim' | 'payment'
const selectedTx       = ref(null)
const txToDelete       = ref(null)
const isDeleting       = ref(false)

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

const DEFAULT_WIDTHS = [90, 120, 130, 260, 180, 140, 210]

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

function requestDelete(tx) {
  txToDelete.value = tx
}

function cancelDelete() {
  if (!isDeleting.value) txToDelete.value = null
}

async function confirmDelete() {
  if (!txToDelete.value) return
  isDeleting.value = true
  const id = txToDelete.value.id
  try {
    await transactionsApi.remove(id)
    transactions.value = transactions.value.filter(t => t.id !== id)
    txToDelete.value = null
    showToast('Transação excluída.')
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

      <!-- Month filter + period display -->
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
            {{ m }}
          </option>
        </select>
        <span v-if="selectedMonth" class="period-range">
          {{ periodLabel(selectedMonth) }}
        </span>
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

    <!-- Empty -->
    <div v-else-if="!transactions.length && !isLoading" class="empty-state">
      <unicon name="file-alt" width="48" height="48" />
      <p>Nenhuma transação encontrada. <RouterLink to="/importar">Importe um extrato</RouterLink>.</p>
    </div>

    <!-- Table -->
    <div v-else class="card table-wrapper" :class="{ 'is-resizing': isResizing }">
      <table class="table">
        <colgroup>
          <col v-for="(w, i) in colWidths" :key="i" :style="{ width: w + 'px' }" />
        </colgroup>
        <thead>
          <tr>
            <th v-for="(label, i) in ['Data','Origem','Operação','Descrição','Categoria','Valor','Ações']" :key="i">
              {{ label }}
              <div
                v-if="i < 6"
                class="resize-handle"
                @mousedown="startResize(i, $event)"
              ></div>
            </th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="tx in transactions"
            :key="tx.id"
            :class="rowClass(tx.type)"
          >
            <td class="col-date">{{ fmtDate(tx.date) }}</td>
            <td class="td-clip">{{ tx.origin }}</td>
            <td class="td-clip">{{ tx.operation }}</td>
            <td>
              <span class="desc-main">{{ tx.translated_description || tx.raw_description }}</span>
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

              <button
                v-if="tx.category_name === 'Outros'"
                class="btn-action btn-action--rule"
                title="Criar regra de parsing"
                @click="openRuleModal(tx)"
              >
                <unicon name="tag-alt" width="14" height="14" />
                Regra
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- ── Modais ── -->
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
      v-if="txToDelete"
      title="Excluir transação?"
      message="Esta ação não pode ser desfeita."
      :detail="txToDelete.translated_description || txToDelete.raw_description"
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
.btn-action--claim   { background: var(--color-accent);}
.btn-action--payment { background: var(--color-success); color: #e7e7e7; }
.btn-action--delete  { background: var(--color-error); color: #e7e7e7; }

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
