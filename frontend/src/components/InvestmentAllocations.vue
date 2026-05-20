<script setup>
import { ref, reactive, computed, watch, onMounted, nextTick } from 'vue'
import { allocationsApi, cdiApi } from '@/services/api.js'
import ConfirmModal from '@/components/ConfirmModal.vue'
import {
  rateToPercent,
  percentToRate,
  yearlyFromCdi,
  monthlyFromYearly,
  yearlyFromMonthly,
  cdiFromYearly,
  round4,
} from '@/utils/investmentRates.js'

const props = defineProps({
  objectives: { type: Array, default: () => [] },
})

const STAR_EMPTY = 'rgba(72, 86, 150, 0.45)'
const STAR_FILLED = '#c57700'

const allocations = ref([])
const isLoading = ref(false)
const isSubmitting = ref(false)
const errorMsg = ref('')
const successMsg = ref('')

const baseCdiYearly = ref(null)
const cdiLoading = ref(false)
const cdiError = ref('')

const showFormModal = ref(false)
const editingId = ref(null)
const itemToDelete = ref(null)

/** Evita loops entre watchers de taxas. */
let rateSyncLock = false

const EMPTY_FORM = () => ({
  objective_id: '',
  bank: '',
  type: '',
  liquidity: '',
  amount: '',
  priority: '',
  cdi_percentage: '',
  monthly_rate: '',
  yearly_rate: '',
  description: '',
})

const form = reactive(EMPTY_FORM())

const formModalTitle = computed(() =>
  editingId.value ? 'Editar alocação' : 'Nova alocação',
)

const brl = (v) =>
  new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(v) || 0)

const pctFmt = (v, digits = 2) =>
  new Intl.NumberFormat('pt-BR', {
    minimumFractionDigits: digits,
    maximumFractionDigits: digits,
  }).format(Number(v) || 0)

const totalInvested = computed(() =>
  allocations.value.reduce((s, row) => s + (Number(row.amount) || 0), 0),
)

function priorityLevel(row) {
  const p = Number(row.priority)
  return Number.isFinite(p) && p >= 1 && p <= 5 ? p : 0
}

function projectedMonthlyYield(row) {
  const amount = Number(row.amount) || 0
  const monthly = Number(row.monthly_rate) || 0
  return amount * monthly
}

function resetForm() {
  Object.assign(form, EMPTY_FORM())
  editingId.value = null
}

function fillFormFromRow(row) {
  editingId.value = row.id
  form.objective_id = row.objective_id ?? ''
  form.bank = row.bank ?? ''
  form.type = row.type ?? ''
  form.liquidity = row.liquidity ?? ''
  form.amount = row.amount != null ? String(row.amount) : ''
  form.priority = row.priority != null ? String(row.priority) : ''
  form.cdi_percentage = row.cdi_percentage != null ? String(row.cdi_percentage) : ''
  form.monthly_rate = row.monthly_rate != null ? rateToPercent(row.monthly_rate) : ''
  form.yearly_rate = row.yearly_rate != null ? rateToPercent(row.yearly_rate) : ''
  form.description = row.description ?? ''
}

function applyRates({ yearlyDec, monthlyDec, cdiPct }) {
  rateSyncLock = true
  if (yearlyDec != null) form.yearly_rate = rateToPercent(yearlyDec)
  if (monthlyDec != null) form.monthly_rate = rateToPercent(monthlyDec)
  if (cdiPct != null) form.cdi_percentage = String(round4(cdiPct))
  nextTick(() => {
    rateSyncLock = false
  })
}

watch(
  () => form.cdi_percentage,
  () => {
    if (rateSyncLock) return
    if (baseCdiYearly.value == null) return
    const yearlyDec = yearlyFromCdi(baseCdiYearly.value, form.cdi_percentage)
    if (yearlyDec == null) return
    applyRates({
      yearlyDec,
      monthlyDec: monthlyFromYearly(yearlyDec),
    })
  },
)

watch(
  () => form.yearly_rate,
  () => {
    if (rateSyncLock) return
    const yearlyDec = percentToRate(form.yearly_rate)
    if (yearlyDec == null) return
    applyRates({
      yearlyDec,
      monthlyDec: monthlyFromYearly(yearlyDec),
      cdiPct: baseCdiYearly.value != null ? cdiFromYearly(baseCdiYearly.value, yearlyDec) : null,
    })
  },
)

watch(
  () => form.monthly_rate,
  () => {
    if (rateSyncLock) return
    const monthlyDec = percentToRate(form.monthly_rate)
    if (monthlyDec == null) return
    const yearlyDec = yearlyFromMonthly(monthlyDec)
    if (yearlyDec == null) return
    applyRates({
      yearlyDec,
      monthlyDec,
      cdiPct: baseCdiYearly.value != null ? cdiFromYearly(baseCdiYearly.value, yearlyDec) : null,
    })
  },
)

async function loadCdi() {
  cdiLoading.value = true
  cdiError.value = ''
  try {
    const { data: body } = await cdiApi.get()
    if (!body?.success) throw new Error(body?.error ?? 'Erro ao carregar CDI.')
    baseCdiYearly.value = Number(body.data?.cdi_annual_rate)
    if (Number.isNaN(baseCdiYearly.value)) {
      throw new Error('Taxa CDI inválida na resposta.')
    }
  } catch (err) {
    cdiError.value = err.message
    baseCdiYearly.value = null
  } finally {
    cdiLoading.value = false
  }
}

async function loadAllocations() {
  isLoading.value = true
  errorMsg.value = ''
  try {
    const { data: body } = await allocationsApi.list()
    if (!body?.success) throw new Error(body?.error ?? 'Erro ao carregar alocações.')
    allocations.value = body.data ?? []
  } catch (err) {
    errorMsg.value = err.message
    allocations.value = []
  } finally {
    isLoading.value = false
  }
}

function buildPayload() {
  const amount = parseFloat(String(form.amount).replace(',', '.'))
  if (Number.isNaN(amount) || amount <= 0) {
    throw new Error('Informe um valor investido positivo.')
  }
  if (!String(form.bank).trim()) {
    throw new Error('Informe a instituição (banco).')
  }

  const priorityRaw = form.priority
  let priority = null
  if (priorityRaw !== '' && priorityRaw != null) {
    priority = parseInt(String(priorityRaw), 10)
    if (priority < 1 || priority > 5) throw new Error('Prioridade deve ser de 1 a 5.')
  }

  return {
    objective_id: form.objective_id === '' ? null : Number(form.objective_id),
    bank: form.bank.trim(),
    type: form.type.trim() || null,
    liquidity: form.liquidity.trim() || null,
    amount,
    priority,
    cdi_percentage: form.cdi_percentage === '' ? null : parseFloat(String(form.cdi_percentage).replace(',', '.')),
    monthly_rate: form.monthly_rate === '' ? null : percentToRate(form.monthly_rate),
    yearly_rate: form.yearly_rate === '' ? null : percentToRate(form.yearly_rate),
    description: form.description.trim() || null,
  }
}

function openCreateModal() {
  resetForm()
  errorMsg.value = ''
  showFormModal.value = true
}

function openEditModal(row) {
  fillFormFromRow(row)
  errorMsg.value = ''
  showFormModal.value = true
}

function closeFormModal() {
  if (isSubmitting.value) return
  showFormModal.value = false
  resetForm()
}

function onFormModalOverlay(e) {
  if (e.target === e.currentTarget) closeFormModal()
}

async function submitForm() {
  isSubmitting.value = true
  errorMsg.value = ''
  try {
    const payload = buildPayload()
    if (editingId.value) {
      await allocationsApi.update(editingId.value, payload)
      showSuccess('Alocação atualizada.')
    } else {
      await allocationsApi.create(payload)
      showSuccess('Alocação registada.')
    }
    showFormModal.value = false
    resetForm()
    await loadAllocations()
  } catch (err) {
    errorMsg.value = err.message
  } finally {
    isSubmitting.value = false
  }
}

function requestDelete(row) {
  itemToDelete.value = row
}

async function confirmDelete() {
  const row = itemToDelete.value
  if (!row) return
  isSubmitting.value = true
  errorMsg.value = ''
  try {
    await allocationsApi.remove(row.id)
    itemToDelete.value = null
    if (editingId.value === row.id) {
      showFormModal.value = false
      resetForm()
    }
    showSuccess('Alocação removida.')
    await loadAllocations()
  } catch (err) {
    errorMsg.value = err.message
  } finally {
    isSubmitting.value = false
  }
}

let successTimer = null
function showSuccess(msg) {
  successMsg.value = msg
  clearTimeout(successTimer)
  successTimer = setTimeout(() => { successMsg.value = '' }, 3500)
}

onMounted(async () => {
  await Promise.all([loadCdi(), loadAllocations()])
})
</script>

<template>
  <div class="allocations">
    <div v-if="successMsg" class="alert alert--success" role="status">
      <unicon name="check-circle" width="16" height="16" />
      {{ successMsg }}
    </div>
    <div v-if="errorMsg" class="alert alert--error" role="alert">
      <unicon name="times-circle" width="16" height="16" />
      {{ errorMsg }}
    </div>

    <div class="allocations-summary panel">
      <div class="allocations-summary__item">
        <span class="allocations-summary__label">CDI base (a.a.)</span>
        <span v-if="cdiLoading" class="allocations-summary__value">Carregando…</span>
        <span v-else-if="cdiError" class="allocations-summary__value allocations-summary__value--warn">
          {{ cdiError }}
        </span>
        <span v-else-if="baseCdiYearly != null" class="allocations-summary__value">
          {{ pctFmt(baseCdiYearly) }}%
        </span>
        <span v-else class="allocations-summary__value">—</span>
      </div>
      <div class="allocations-summary__item">
        <span class="allocations-summary__label">Total investido</span>
        <span class="allocations-summary__value">{{ brl(totalInvested) }}</span>
      </div>
      <div class="allocations-summary__item">
        <span class="allocations-summary__label">Contas</span>
        <span class="allocations-summary__value">{{ allocations.length }}</span>
      </div>
    </div>

    <section class="card allocations-table-card">
      <header class="allocations-table-card__header">
        <h3 class="allocations-table-card__title">Consolidado de alocações</h3>
        <button type="button" class="btn btn--primary btn--sm" @click="openCreateModal">
          <unicon name="plus" width="16" height="16" />
          Nova alocação
        </button>
      </header>

      <div v-if="isLoading" class="allocations-loading">
        <span class="spinner-ui spinner-ui--md" aria-hidden="true"></span>
        Carregando alocações…
      </div>

      <div v-else class="table-wrap">
        <table class="table table--allocations">
          <thead>
            <tr>
              <th>Prioridade</th>
              <th>Instituição</th>
              <th>Finalidade</th>
              <th>Tipo</th>
              <th>Liquidez</th>
              <th>Valor</th>
              <th>% CDI</th>
              <th>Taxa a.a.</th>
              <th>Prev. mês</th>
              <th class="col-actions">Ações</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="allocations.length === 0">
              <td colspan="10" class="table-empty">Nenhuma alocação cadastrada.</td>
            </tr>
            <tr v-for="row in allocations" :key="row.id">
              <td class="col-priority">
                <span
                  class="star-rating"
                  :aria-label="priorityLevel(row) ? `Prioridade ${priorityLevel(row)} de 5` : 'Sem prioridade'"
                >
                  <unicon
                    v-for="n in 5"
                    :key="n"
                    name="star"
                    width="18"
                    height="18"
                    :icon-style="n <= priorityLevel(row) ? 'solid' : 'line'"
                    :fill="n <= priorityLevel(row) ? STAR_FILLED : STAR_EMPTY"
                  />
                </span>
              </td>
              <td>{{ row.bank }}</td>
              <td>{{ row.objective_name || '—' }}</td>
              <td>{{ row.type || '—' }}</td>
              <td>{{ row.liquidity || '—' }}</td>
              <td class="col-amount">{{ brl(row.amount) }}</td>
              <td>{{ row.cdi_percentage != null ? `${pctFmt(row.cdi_percentage)}%` : '—' }}</td>
              <td>{{ row.yearly_rate != null ? `${rateToPercent(row.yearly_rate)}%` : '—' }}</td>
              <td class="col-yield">{{ brl(projectedMonthlyYield(row)) }}</td>
              <td class="col-actions">
                <div class="row-actions">
                  <button type="button" class="btn-icon" title="Editar" @click="openEditModal(row)">
                    <unicon name="edit-alt" width="16" height="16" />
                  </button>
                  <button type="button" class="btn-icon btn-icon--danger" title="Excluir" @click="requestDelete(row)">
                    <unicon name="trash-alt" width="16" height="16" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Modal: criar / editar alocação -->
    <Transition name="modal">
      <div
        v-if="showFormModal"
        class="modal-overlay"
        @click="onFormModalOverlay"
      >
        <div
          class="modal-card modal-card--alloc"
          role="dialog"
          aria-modal="true"
          aria-labelledby="alloc-modal-title"
        >
          <header class="modal-card__header">
            <h3 id="alloc-modal-title" class="modal-card__title">{{ formModalTitle }}</h3>
            <button
              type="button"
              class="btn-icon"
              aria-label="Fechar"
              :disabled="isSubmitting"
              @click="closeFormModal"
            >
              <unicon name="times" width="18" height="18" />
            </button>
          </header>

          <form class="modal-card__body alloc-form" @submit.prevent="submitForm" novalidate>
            <div class="alloc-form__grid">
              <div class="form-group">
                <label class="form-label" for="alloc-bank">Instituição *</label>
                <input id="alloc-bank" v-model="form.bank" type="text" class="form-control" required maxlength="120" />
              </div>
              <div class="form-group">
                <label class="form-label" for="alloc-objective">Finalidade</label>
                <select id="alloc-objective" v-model="form.objective_id" class="form-control app-select">
                  <option value="">— Nenhuma —</option>
                  <option v-for="obj in objectives" :key="obj.id" :value="obj.id">{{ obj.name }}</option>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label" for="alloc-type">Tipo</label>
                <input id="alloc-type" v-model="form.type" type="text" class="form-control" placeholder="CDI, Fundo…" />
              </div>
              <div class="form-group">
                <label class="form-label" for="alloc-liquidity">Liquidez</label>
                <input id="alloc-liquidity" v-model="form.liquidity" type="text" class="form-control" placeholder="Diária, data…" />
              </div>
              <div class="form-group">
                <label class="form-label" for="alloc-amount">Valor investido (R$) *</label>
                <input id="alloc-amount" v-model="form.amount" type="number" class="form-control" min="0.01" step="0.01" required />
              </div>
              <div class="form-group">
                <label class="form-label" for="alloc-priority">Prioridade (1–5)</label>
                <select id="alloc-priority" v-model="form.priority" class="form-control app-select">
                  <option value="">—</option>
                  <option v-for="n in 5" :key="n" :value="String(n)">{{ n }} {{ n === 1 ? 'estrela' : 'estrelas' }}</option>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label" for="alloc-cdi">% CDI</label>
                <input id="alloc-cdi" v-model="form.cdi_percentage" type="number" class="form-control" min="0" step="0.01" placeholder="100" />
              </div>
              <div class="form-group">
                <label class="form-label" for="alloc-yearly">Taxa anual (% a.a.)</label>
                <input id="alloc-yearly" v-model="form.yearly_rate" type="number" class="form-control" min="0" step="0.0001" />
              </div>
              <div class="form-group">
                <label class="form-label" for="alloc-monthly">Taxa mensal (% a.m.)</label>
                <input id="alloc-monthly" v-model="form.monthly_rate" type="number" class="form-control" min="0" step="0.0001" />
              </div>
              <div class="form-group alloc-form__field--full">
                <label class="form-label" for="alloc-desc">Descrição</label>
                <input id="alloc-desc" v-model.trim="form.description" type="text" class="form-control" maxlength="300" />
              </div>
            </div>

            <footer class="modal-card__footer">
              <button type="button" class="btn btn--outline" :disabled="isSubmitting" @click="closeFormModal">
                Cancelar
              </button>
              <button type="submit" class="btn btn--primary" :disabled="isSubmitting">
                <span v-if="isSubmitting" class="spinner-ui spinner-ui--sm" aria-hidden="true"></span>
                {{ isSubmitting ? 'Salvando…' : editingId ? 'Salvar alterações' : 'Adicionar alocação' }}
              </button>
            </footer>
          </form>
        </div>
      </div>
    </Transition>

    <ConfirmModal
      v-if="itemToDelete"
      title="Excluir alocação"
      :message="`Remover a conta em ${itemToDelete.bank}?`"
      confirm-label="Excluir"
      :loading="isSubmitting"
      @confirm="confirmDelete"
      @close="itemToDelete = null"
    />
  </div>
</template>

<style scoped>
.allocations {
  display: flex;
  flex-direction: column;
  gap: 16px;
  width: 100%;
}

.allocations-summary {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 16px;
  padding: 16px 18px;
}

@media (max-width: 640px) {
  .allocations-summary {
    grid-template-columns: 1fr;
  }
}

.allocations-summary__label {
  display: block;
  font-size: 0.78rem;
  color: var(--color-text-muted);
  text-transform: uppercase;
  letter-spacing: 0.04em;
  margin-bottom: 4px;
}

.allocations-summary__value {
  font-size: 1.1rem;
  font-weight: 700;
}

.allocations-summary__value--warn {
  color: var(--color-error-text);
  font-size: 0.88rem;
  font-weight: 500;
}

.allocations-table-card {
  width: 100%;
  margin-bottom: 0;
}

.allocations-table-card__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
  padding: 16px 18px 12px;
}

.allocations-table-card__title {
  margin: 0;
  font-size: 1rem;
  font-weight: 700;
}

.allocations-loading {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  padding: 32px;
  color: var(--color-text-muted);
}

.table-wrap {
  overflow-x: auto;
  padding: 0 12px 16px;
  width: 100%;
}

.table--allocations {
  width: 100%;
}

.table-empty {
  text-align: center;
  color: var(--color-text-muted);
  padding: 28px !important;
}

.col-priority {
  white-space: nowrap;
}

.star-rating {
  display: inline-flex;
  gap: 1px;
  align-items: center;
  line-height: 0;
}

.col-amount,
.col-yield {
  white-space: nowrap;
  font-weight: 600;
}

.col-yield {
  color: var(--color-success-lit, #5ee86a);
}

.col-actions {
  width: 96px;
  text-align: center;
}

.row-actions {
  display: flex;
  gap: 6px;
  justify-content: center;
}

.btn-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 34px;
  height: 34px;
  padding: 0;
  border: 1px solid var(--color-border-light);
  border-radius: var(--radius-sm);
  background: var(--color-bg-elevated);
  cursor: pointer;
}

.btn-icon:hover:not(:disabled) {
  border-color: var(--color-accent);
}

.btn-icon--danger:hover:not(:disabled) :deep(svg) {
  fill: var(--color-error-text);
}

/* Modal */
.modal-overlay {
  position: fixed;
  inset: 0;
  z-index: 200;
  background: rgba(0, 6, 20, 0.75);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
}

.modal-card {
  width: 100%;
  max-width: 440px;
  background: var(--color-bg-secondary);
  border: 1px solid var(--color-border-light);
  border-radius: var(--radius-md);
  box-shadow: 0 16px 48px rgba(0, 0, 0, 0.45);
}

.modal-card--alloc {
  max-width: 560px;
}

.modal-card__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 16px 18px;
  border-bottom: 1px solid var(--color-border-light);
}

.modal-card__title {
  margin: 0;
  font-size: 1.05rem;
  font-weight: 700;
}

.modal-card__body {
  padding: 16px 18px;
}

.modal-card__footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding-top: 8px;
  margin-top: 4px;
}

.alloc-form__grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

.alloc-form__field--full {
  grid-column: 1 / -1;
}

@media (max-width: 520px) {
  .alloc-form__grid {
    grid-template-columns: 1fr;
  }

  .alloc-form__field--full {
    grid-column: auto;
  }
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-label {
  font-size: 0.82rem;
  font-weight: 600;
  color: var(--color-text-muted);
}

.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.2s ease;
}

.modal-enter-active .modal-card,
.modal-leave-active .modal-card {
  transition: transform 0.2s ease;
}

.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}

.modal-enter-from .modal-card,
.modal-leave-to .modal-card {
  transform: scale(0.96) translateY(8px);
}
</style>
