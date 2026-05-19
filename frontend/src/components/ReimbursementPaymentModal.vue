<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { reimbursementsApi } from '@/services/api.js'

// ── Props / Emits ──────────────────────────────────────────────────────────
const props = defineProps({
  transaction: { type: Object, required: true },
})

const emit = defineEmits(['close', 'saved'])

// ── State ──────────────────────────────────────────────────────────────────
const claims       = ref([])
const isLoading    = ref(true)
const isSubmitting = ref(false)
const errorMsg     = ref('')
const successMsg   = ref('')

/**
 * Mapa: claim_id → valor digitado pelo usuário
 * @type {Record<number, string>}
 */
const allocations = reactive({})

// ── Computed ───────────────────────────────────────────────────────────────
const totalAllocated = computed(() =>
  Object.values(allocations).reduce((sum, v) => sum + (parseFloat(v) || 0), 0)
)

const remaining = computed(() =>
  props.transaction.amount - totalAllocated.value
)

const hasAnyAllocation = computed(() =>
  Object.values(allocations).some(v => parseFloat(v) > 0)
)

// ── Load claims ────────────────────────────────────────────────────────────
onMounted(async () => {
  try {
    const { data } = await reimbursementsApi.activeClaims()
    claims.value = data.data ?? data   // API pode retornar { data: [...] } ou [...]
    claims.value.forEach(c => {
      allocations[c.id] = ''
    })
  } catch (err) {
    errorMsg.value = err.message
  } finally {
    isLoading.value = false
  }
})

// ── Helpers ────────────────────────────────────────────────────────────────
function fmt(v) {
  return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(v))
}

function fmtDate(d) {
  if (!d) return '—'
  const [y, m, day] = d.split('-')
  return day ? `${day}/${m}/${y}` : d
}

function statusClass(status) {
  return {
    Aberto:  'badge--aberto',
    Parcial: 'badge--parcial',
    Quitado: 'badge--quitado',
  }[status] ?? ''
}

function fillMaxFor(claimId) {
  const claim = claims.value.find(c => c.id === claimId)
  if (!claim) return
  const maxAmount = Math.min(claim.expected_amount, props.transaction.amount)
  allocations[claimId] = String(maxAmount.toFixed(2))
}

// ── Submit ─────────────────────────────────────────────────────────────────
async function submit() {
  errorMsg.value = ''

  const allocs = Object.entries(allocations)
    .map(([id, v]) => ({ claim_id: Number(id), paid_amount: parseFloat(v) || 0 }))
    .filter(a => a.paid_amount > 0)

  if (!allocs.length) {
    errorMsg.value = 'Informe o valor a alocar em pelo menos uma pendência.'
    return
  }

  if (totalAllocated.value > props.transaction.amount + 0.01) {
    errorMsg.value = `Total alocado (${fmt(totalAllocated.value)}) excede o valor da entrada (${fmt(props.transaction.amount)}).`
    return
  }

  isSubmitting.value = true

  try {
    await reimbursementsApi.registerPayment({
      income_transaction_id: props.transaction.id,
      allocations: allocs,
    })
    successMsg.value = 'Pagamentos registados com sucesso!'
    setTimeout(() => emit('saved'), 1400)
  } catch (err) {
    errorMsg.value = err.message
  } finally {
    isSubmitting.value = false
  }
}

function onOverlay(e) {
  if (e.target === e.currentTarget) emit('close')
}
</script>

<template>
  <Transition name="modal" appear>
    <div class="overlay" @click="onOverlay" role="dialog" aria-modal="true" aria-label="Vincular Reembolso">
      <div class="modal">
        <header class="modal-header">
          <h3 class="modal-title">
            <unicon name="link" width="18" height="18" />
            Vincular Reembolso
          </h3>
          <button class="btn-close" @click="$emit('close')" aria-label="Fechar">
            <unicon name="times" width="18" height="18" />
          </button>
        </header>

        <div class="modal-body">
          <!-- Entrada de referência -->
          <div class="ref-box">
            <span class="ref-label">Entrada recebida</span>
            <p class="ref-text">{{ transaction.translated_description || transaction.raw_description }}</p>
            <div class="ref-row">
              <span class="ref-meta">{{ fmtDate(transaction.date) }} · {{ transaction.origin }}</span>
              <span class="ref-amount ref-amount--in">{{ fmt(transaction.amount) }}</span>
            </div>
          </div>

          <!-- Feedback -->
          <div v-if="successMsg" class="alert alert--success">
            <unicon name="check-circle" width="14" height="14" />
            {{ successMsg }}
          </div>
          <div v-if="errorMsg" class="alert alert--error">
            <unicon name="times-circle" width="14" height="14" />
            {{ errorMsg }}
          </div>

          <!-- Loading -->
          <div v-if="isLoading" class="loading-line">
            <span class="spinner spinner--dark" aria-hidden="true"></span>
            Carregando pendências…
          </div>

          <!-- Sem pendências -->
          <div v-else-if="!claims.length" class="empty-claims">
            <unicon name="inbox" width="40" height="40" />
            <p>Não há pendências em aberto para vincular.</p>
          </div>

          <!-- Tabela de claims -->
          <template v-else>
            <p class="claims-hint">
              Distribua o valor de <strong>{{ fmt(transaction.amount) }}</strong> entre as pendências abaixo:
            </p>

            <div class="claims-list">
              <div
                v-for="claim in claims"
                :key="claim.id"
                class="claim-row"
                :class="{ 'claim-row--allocated': parseFloat(allocations[claim.id]) > 0 }"
              >
                <div class="claim-info">
                  <div class="claim-top">
                    <span class="claim-desc">{{ claim.description }}</span>
                    <span class="badge" :class="statusClass(claim.status)">
                      {{ claim.status }}
                    </span>
                  </div>
                  <div class="claim-meta">
                    <span>{{ claim.translated_description || '—' }}</span>
                    <span class="claim-date">{{ claim.month_year }}</span>
                    <span class="claim-expected">Esperado: {{ fmt(claim.expected_amount) }}</span>
                  </div>
                </div>

                <div class="claim-input-group">
                  <div class="input-prefix-wrap">
                    <span class="input-prefix">R$</span>
                    <input
                      v-model="allocations[claim.id]"
                      type="number"
                      step="0.01"
                      min="0"
                      :max="claim.expected_amount"
                      class="form-control form-control--prefixed form-control--sm"
                      placeholder="0,00"
                    />
                  </div>
                  <button
                    type="button"
                    class="btn-max"
                    title="Preencher valor máximo"
                    @click="fillMaxFor(claim.id)"
                  >
                    MAX
                  </button>
                </div>
              </div>
            </div>

            <!-- Resumo -->
            <div class="summary-bar" :class="remaining < -0.01 ? 'summary-bar--over' : ''">
              <span>Total alocado: <strong>{{ fmt(totalAllocated) }}</strong></span>
              <span>Restante: <strong :class="remaining < -0.01 ? 'text-red' : ''">{{ fmt(remaining) }}</strong></span>
            </div>

            <footer class="modal-footer">
              <button type="button" class="btn btn--outline" @click="$emit('close')" :disabled="isSubmitting">
                Cancelar
              </button>
              <button
                class="btn btn--primary"
                :disabled="isSubmitting || !hasAnyAllocation || !!successMsg"
                @click="submit"
              >
                <span v-if="isSubmitting" class="spinner" aria-hidden="true"></span>
                {{ isSubmitting ? 'Registando…' : 'Registar Pagamentos' }}
              </button>
            </footer>
          </template>
        </div>
      </div>
    </div>
  </Transition>
</template>

<style scoped>
.overlay { position: fixed; inset: 0; background: rgba(0,0,0,.6); display: flex; align-items: center; justify-content: center; z-index: 500; padding: 20px; backdrop-filter: blur(2px); }

.modal { background: var(--color-surface); border-radius: var(--radius-lg); width: 100%; max-width: 560px; max-height: 90vh; box-shadow: var(--shadow-md); overflow: hidden; display: flex; flex-direction: column; border: 1px solid var(--color-border-dark); }

.modal-header { display: flex; align-items: center; justify-content: space-between; padding: 18px 24px 14px; border-bottom: 1px solid var(--color-border-dark); flex-shrink: 0; }
.modal-title { font-size: 1.05rem; font-weight: 700; color: var(--color-text); display: flex; align-items: center; gap: 8px; }
.modal-title :deep(svg) { fill: var(--color-accent); }
.btn-close { background: none; border: none; color: var(--color-text-subtle); cursor: pointer; padding: 4px; border-radius: var(--radius-sm); display: flex; align-items: center; transition: background .15s, color .15s; }
.btn-close :deep(svg) { fill: currentColor; }
.btn-close:hover { background: var(--color-surface-elevated); color: var(--color-text); }

.modal-body { padding: 20px 24px; overflow-y: auto; flex: 1; }

.ref-box { background: var(--color-success-bg); border: 1px solid var(--color-success); border-radius: var(--radius-sm); padding: 12px 16px; margin-bottom: 20px; display: flex; flex-direction: column; gap: 4px; }
.ref-label  { font-size: .72rem; font-weight: 600; color: var(--color-text-subtle); text-transform: uppercase; letter-spacing: .4px; }
.ref-text   { font-size: .92rem; font-weight: 500; color: var(--color-text); word-break: break-word; }
.ref-row    { display: flex; justify-content: space-between; align-items: center; }
.ref-meta   { font-size: .78rem; color: var(--color-text-subtle); }
.ref-amount { font-size: .95rem; font-weight: 700; }
.ref-amount--in { color: var(--color-success); }

.alert { padding: 10px 14px; border-radius: var(--radius-sm); font-size: .88rem; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
.alert--error   { background: var(--color-error-bg); color: var(--color-error); border: 1px solid var(--color-error); }
.alert--success { background: var(--color-success-bg); color: var(--color-success); }
.alert :deep(svg) { fill: currentColor; flex-shrink: 0; }

.loading-line { display: flex; align-items: center; gap: 10px; color: var(--color-text-subtle); font-size: .9rem; padding: 20px 0; }
.empty-claims { display: flex; flex-direction: column; align-items: center; gap: 8px; padding: 32px; color: var(--color-text-subtle); font-size: .9rem; text-align: center; }

.claims-hint { font-size: .88rem; color: var(--color-text-muted); margin-bottom: 14px; }
.claims-list { display: flex; flex-direction: column; gap: 10px; margin-bottom: 16px; }
.claim-row { border: 1px solid var(--color-border-dark); border-radius: var(--radius-md); padding: 12px 14px; display: flex; align-items: center; justify-content: space-between; gap: 12px; transition: border-color .15s; flex-wrap: wrap; }
.claim-row--allocated { border-color: var(--color-accent); background: rgba(249,168,38,.06); }
.claim-info { flex: 1; min-width: 0; }
.claim-top  { display: flex; align-items: center; gap: 8px; margin-bottom: 4px; }
.claim-desc { font-size: .9rem; font-weight: 600; color: var(--color-text); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.claim-meta { display: flex; gap: 10px; flex-wrap: wrap; font-size: .76rem; color: var(--color-text-muted); }
.claim-expected { font-weight: 600; color: var(--color-error); }

.badge { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: .72rem; font-weight: 600; flex-shrink: 0; }
.badge--aberto  { background: var(--color-warning-bg); color: var(--color-warning); }
.badge--parcial { background: rgba(0, 49, 163, 0.2); color: var(--color-accent-secondary); }
.badge--quitado { background: var(--color-success-bg); color: var(--color-success); }

.claim-input-group { display: flex; align-items: center; gap: 6px; flex-shrink: 0; }
.input-prefix-wrap { position: relative; }
.input-prefix { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); font-size: .85rem; color: var(--color-text-muted); pointer-events: none; }

.form-control { padding: 7px 10px; border: 1px solid var(--color-border-light); border-radius: var(--radius-sm); font-size: .88rem; color: var(--color-text); background: var(--color-surface-input); transition: border-color .15s; outline: none; font-family: inherit; }
.form-control:focus { border-color: var(--color-accent); }
.form-control--prefixed { padding-left: 28px; }
.form-control--sm { width: 110px; }

.btn-max { background: var(--color-surface-elevated); color: var(--color-accent-secondary); border: 1px solid var(--color-accent-secondary); border-radius: var(--radius-sm); padding: 5px 9px; font-size: .72rem; font-weight: 700; cursor: pointer; transition: background .15s; white-space: nowrap; }
.btn-max:hover { background: rgba(0, 49, 163, 0.2); }

.summary-bar { display: flex; justify-content: space-between; background: var(--color-surface-elevated); border: 1px solid var(--color-border-dark); border-radius: var(--radius-sm); padding: 10px 14px; font-size: .85rem; color: var(--color-text-muted); margin-bottom: 16px; }
.summary-bar--over { background: var(--color-error-bg); border-color: var(--color-error); }
.text-red { color: var(--color-error); }

.modal-footer { display: flex; justify-content: flex-end; gap: 10px; padding-top: 16px; border-top: 1px solid var(--color-border-dark); }
.btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 18px; border-radius: var(--radius-sm); font-size: .88rem; font-weight: 600; cursor: pointer; border: none; font-family: inherit; transition: filter .15s; }
.btn:disabled { opacity: .6; cursor: not-allowed; }
.btn:not(:disabled):hover { filter: brightness(1.1); }
.btn--primary { background: var(--color-accent); color: var(--color-on-accent); }
.btn--outline { background: transparent; border: 1.5px solid var(--color-border-dark); color: var(--color-text-muted); }

.spinner { display: inline-block; width: 14px; height: 14px; border: 2px solid var(--color-border-dark); border-top-color: var(--color-accent); border-radius: 50%; animation: spin .7s linear infinite; }
.spinner--dark { border-color: var(--color-border-dark); border-top-color: var(--color-accent); }
@keyframes spin { to { transform: rotate(360deg); } }

.modal-enter-active, .modal-leave-active { transition: opacity .2s ease; }
.modal-enter-active .modal, .modal-leave-active .modal { transition: transform .2s ease, opacity .2s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.modal-enter-from .modal, .modal-leave-to .modal { transform: scale(.95) translateY(-10px); opacity: 0; }
</style>
