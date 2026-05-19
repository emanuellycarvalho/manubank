<script setup>
import { reactive, ref } from 'vue'
import { reimbursementsApi } from '@/services/api.js'

// ── Props / Emits ──────────────────────────────────────────────────────────
const props = defineProps({
  transaction: { type: Object, required: true },
})

const emit = defineEmits(['close', 'saved'])

// ── State ──────────────────────────────────────────────────────────────────
const isSubmitting = ref(false)
const errorMsg     = ref('')
const successMsg   = ref('')

const form = reactive({
  expected_amount: props.transaction.amount ?? '',
  description:     '',
})

// ── Formatting ─────────────────────────────────────────────────────────────
function fmt(v) {
  return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(v))
}

function fmtDate(d) {
  if (!d) return '—'
  const [y, m, day] = d.split('-')
  return day ? `${day}/${m}/${y}` : d
}

// ── Submit ─────────────────────────────────────────────────────────────────
async function submit() {
  errorMsg.value = ''

  const amount = parseFloat(String(form.expected_amount).replace(',', '.'))

  if (isNaN(amount) || amount <= 0) {
    errorMsg.value = 'Informe um valor esperado válido e positivo.'
    return
  }

  if (!form.description.trim()) {
    errorMsg.value = 'A descrição é obrigatória.'
    return
  }

  isSubmitting.value = true

  try {
    await reimbursementsApi.createClaim({
      transaction_id:  props.transaction.id,
      expected_amount: amount,
      description:     form.description.trim(),
    })
    successMsg.value = 'Pendência criada com sucesso!'
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
    <div class="overlay" @click="onOverlay" role="dialog" aria-modal="true" aria-label="Gerar Pendência">
      <div class="modal">
        <header class="modal-header">
          <h3 class="modal-title">
            <unicon name="clipboard-alt" width="18" height="18" />
            Gerar Pendência de Reembolso
          </h3>
          <button class="btn-close" @click="$emit('close')" aria-label="Fechar">
            <unicon name="times" width="18" height="18" />
          </button>
        </header>

        <div class="modal-body">
          <!-- Transação de referência -->
          <div class="ref-box">
            <span class="ref-label">Despesa vinculada</span>
            <p class="ref-text">{{ transaction.translated_description || transaction.raw_description }}</p>
            <div class="ref-row">
              <span class="ref-meta">{{ fmtDate(transaction.date) }} · {{ transaction.origin }}</span>
              <span class="ref-amount">{{ fmt(transaction.amount) }}</span>
            </div>
          </div>

          <div v-if="successMsg" class="alert alert--success">
            <unicon name="check-circle" width="14" height="14" />
            {{ successMsg }}
          </div>
          <div v-if="errorMsg" class="alert alert--error">
            <unicon name="times-circle" width="14" height="14" />
            {{ errorMsg }}
          </div>

          <form @submit.prevent="submit" novalidate>
            <div class="form-group">
              <label class="form-label" for="claim-amount">
                Valor esperado *
                <span class="form-hint">Quanto você espera receber de volta</span>
              </label>
              <div class="input-prefix-wrap">
                <span class="input-prefix">R$</span>
                <input
                  id="claim-amount"
                  v-model="form.expected_amount"
                  type="number"
                  step="0.01"
                  min="0.01"
                  class="form-control form-control--prefixed"
                  placeholder="0,00"
                  required
                />
              </div>
            </div>

            <div class="form-group">
              <label class="form-label" for="claim-desc">
                Descrição *
                <span class="form-hint">Contexto do reembolso (ex: "Jantar com João")</span>
              </label>
              <textarea
                id="claim-desc"
                v-model="form.description"
                class="form-control form-control--textarea"
                placeholder="Descreva o motivo do reembolso…"
                rows="3"
                required
              ></textarea>
            </div>

            <footer class="modal-footer">
              <button type="button" class="btn btn--outline" @click="$emit('close')" :disabled="isSubmitting">
                Cancelar
              </button>
              <button type="submit" class="btn btn--primary" :disabled="isSubmitting || !!successMsg">
                <span v-if="isSubmitting" class="spinner" aria-hidden="true"></span>
                {{ isSubmitting ? 'Salvando…' : 'Gerar Pendência' }}
              </button>
            </footer>
          </form>
        </div>
      </div>
    </div>
  </Transition>
</template>

<style scoped>
.overlay { position: fixed; inset: 0; background: rgba(0,0,0,.6); display: flex; align-items: center; justify-content: center; z-index: 500; padding: 20px; backdrop-filter: blur(2px); }

.modal { background: var(--color-surface); border-radius: var(--radius-lg); width: 100%; max-width: 480px; box-shadow: var(--shadow-md); overflow: hidden; border: 1px solid var(--color-border-dark); }

.modal-header { display: flex; align-items: center; justify-content: space-between; padding: 18px 24px 14px; border-bottom: 1px solid var(--color-border-dark); }

.modal-title { font-size: 1.05rem; font-weight: 700; color: var(--color-text); display: flex; align-items: center; gap: 8px; }
.modal-title :deep(svg) { fill: var(--color-accent); }

.btn-close { background: none; border: none; color: var(--color-text-subtle); cursor: pointer; padding: 4px; border-radius: var(--radius-sm); display: flex; align-items: center; transition: background .15s, color .15s; }
.btn-close :deep(svg) { fill: currentColor; }
.btn-close:hover { background: var(--color-surface-elevated); color: var(--color-text); }

.modal-body { padding: 20px 24px; }

.ref-box { background: var(--color-surface-elevated); border: 1px solid var(--color-border-dark); border-radius: var(--radius-sm); padding: 12px 16px; margin-bottom: 20px; display: flex; flex-direction: column; gap: 4px; }
.ref-label  { font-size: .72rem; font-weight: 600; color: var(--color-text-subtle); text-transform: uppercase; letter-spacing: .4px; }
.ref-text   { font-size: .92rem; font-weight: 500; color: var(--color-text); word-break: break-word; }
.ref-row    { display: flex; justify-content: space-between; align-items: center; }
.ref-meta   { font-size: .78rem; color: var(--color-text-subtle); }
.ref-amount { font-size: .9rem; font-weight: 700; color: var(--color-error); }

.alert { padding: 10px 14px; border-radius: var(--radius-sm); font-size: .88rem; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
.alert--error   { background: var(--color-error-bg); color: var(--color-error); border: 1px solid var(--color-error); }
.alert--success { background: var(--color-success-bg); color: var(--color-success); }
.alert :deep(svg) { fill: currentColor; flex-shrink: 0; }

.form-group { margin-bottom: 16px; }
.form-label { display: flex; flex-direction: column; gap: 2px; font-size: .85rem; font-weight: 600; color: var(--color-text-muted); margin-bottom: 6px; }
.form-hint  { font-size: .75rem; font-weight: 400; color: var(--color-text-subtle); }

.form-control { width: 100%; padding: 9px 12px; border: 1px solid var(--color-border-light); border-radius: var(--radius-sm); font-size: .9rem; color: var(--color-text); background: var(--color-surface-input); transition: border-color .15s, box-shadow .15s; outline: none; font-family: inherit; }
.form-control:focus { border-color: var(--color-accent); box-shadow: 0 0 0 3px rgba(249,168,38,.15); }
.form-control--textarea { resize: vertical; min-height: 80px; }

.input-prefix-wrap { position: relative; }
.input-prefix { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); font-size: .9rem; color: var(--color-text-muted); pointer-events: none; }
.form-control--prefixed { padding-left: 32px; }

.modal-footer { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--color-border-dark); }

.btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 18px; border-radius: var(--radius-sm); font-size: .88rem; font-weight: 600; cursor: pointer; border: none; font-family: inherit; transition: filter .15s; }
.btn:disabled { opacity: .6; cursor: not-allowed; }
.btn:not(:disabled):hover { filter: brightness(1.1); }
.btn--primary { background: var(--color-accent); color: var(--color-on-accent); }
.btn--outline { background: transparent; border: 1.5px solid var(--color-border-dark); color: var(--color-text-muted); }

.spinner { display: inline-block; width: 14px; height: 14px; border: 2px solid var(--color-border-dark); border-top-color: var(--color-accent); border-radius: 50%; animation: spin .7s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

.modal-enter-active, .modal-leave-active { transition: opacity .2s ease; }
.modal-enter-active .modal, .modal-leave-active .modal { transition: transform .2s ease, opacity .2s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.modal-enter-from .modal, .modal-leave-to .modal { transform: scale(.95) translateY(-10px); opacity: 0; }
</style>
