<script setup>
import { ref, reactive, watch } from 'vue'
import { investmentsApi } from '@/services/api.js'
import { toIsoDate } from '@/utils/dates.js'

const props = defineProps({
  open: { type: Boolean, default: false },
  objectiveId: { type: Number, default: null },
  objectiveName: { type: String, default: '' },
  /** Ex.: nome do banco ao lançar a partir do consolidado */
  contextLabel: { type: String, default: '' },
  defaults: {
    type: Object,
    default: () => ({}),
  },
})

const emit = defineEmits(['close', 'success'])

const EMPTY = () => ({
  date: toIsoDate(new Date()),
  type: 'entrada',
  amount: '',
  description: '',
})

const form = reactive(EMPTY())
const isSubmitting = ref(false)
const localError = ref('')

function resetForm() {
  Object.assign(form, EMPTY(), props.defaults)
}

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      resetForm()
      localError.value = ''
    }
  },
)

function parsePositiveAmount(raw) {
  const n = parseFloat(String(raw).replace(',', '.'))
  if (Number.isNaN(n) || n <= 0) return null
  return n
}

function close() {
  if (isSubmitting.value) return
  emit('close')
}

function onOverlay(e) {
  if (e.target === e.currentTarget) close()
}

async function submit() {
  if (!props.objectiveId) {
    localError.value = 'Objetivo não definido.'
    return
  }

  const amount = parsePositiveAmount(form.amount)
  if (!form.date) {
    localError.value = 'Informe a data do lançamento.'
    return
  }
  if (!amount) {
    localError.value = 'Informe um valor positivo.'
    return
  }

  isSubmitting.value = true
  localError.value = ''

  try {
    await investmentsApi.addEntry({
      objective_id: props.objectiveId,
      type: form.type,
      amount,
      date: form.date,
      description: form.description.trim(),
    })
    emit('success')
    emit('close')
  } catch (err) {
    localError.value = err.message
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <Transition name="modal">
    <div v-if="open" class="modal-overlay" @click="onOverlay">
      <div
        class="modal-card modal-card--entry"
        role="dialog"
        aria-modal="true"
        aria-labelledby="entry-modal-title"
      >
        <header class="modal-card__header">
          <h3 id="entry-modal-title" class="modal-card__title">Novo lançamento</h3>
          <button
            type="button"
            class="btn-icon"
            aria-label="Fechar"
            :disabled="isSubmitting"
            @click="close"
          >
            <unicon name="times" width="18" height="18" />
          </button>
        </header>

        <form class="modal-card__body entry-form" @submit.prevent="submit" novalidate>
          <p v-if="contextLabel || objectiveName" class="entry-modal__context">
            <template v-if="contextLabel">
              <strong>{{ contextLabel }}</strong>
              <span v-if="objectiveName"> → {{ objectiveName }}</span>
            </template>
            <template v-else-if="objectiveName">
              Objetivo: <strong>{{ objectiveName }}</strong>
            </template>
          </p>

          <p v-if="localError" class="entry-modal__error" role="alert">{{ localError }}</p>

          <div class="entry-form__grid">
            <div class="form-group entry-form__field">
              <label class="form-label" for="entry-modal-date">Data</label>
              <input
                id="entry-modal-date"
                v-model="form.date"
                type="date"
                class="form-control"
                required
              />
            </div>
            <div class="form-group entry-form__field">
              <label class="form-label" for="entry-modal-type">Tipo</label>
              <select id="entry-modal-type" v-model="form.type" class="form-control app-select">
                <option value="entrada">Entrada</option>
                <option value="saída">Saída</option>
              </select>
            </div>
            <div class="form-group entry-form__field entry-form__field--full">
              <label class="form-label" for="entry-modal-amount">Valor (R$)</label>
              <input
                id="entry-modal-amount"
                v-model="form.amount"
                type="number"
                class="form-control"
                min="0.01"
                step="0.01"
                placeholder="0,00"
                required
                autofocus
              />
            </div>
            <div class="form-group entry-form__field entry-form__field--full">
              <label class="form-label" for="entry-modal-desc">Descrição</label>
              <input
                id="entry-modal-desc"
                v-model.trim="form.description"
                type="text"
                class="form-control"
                placeholder="Opcional"
                maxlength="200"
              />
            </div>
          </div>

          <footer class="modal-card__footer">
            <button type="button" class="btn btn--outline" :disabled="isSubmitting" @click="close">
              Cancelar
            </button>
            <button type="submit" class="btn btn--primary" :disabled="isSubmitting">
              <span v-if="isSubmitting" class="spinner-ui spinner-ui--sm" aria-hidden="true"></span>
              {{ isSubmitting ? 'Salvando…' : 'Registrar lançamento' }}
            </button>
          </footer>
        </form>
      </div>
    </div>
  </Transition>
</template>

<style scoped>
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

.entry-modal__context {
  margin: 0 0 12px;
  font-size: 0.88rem;
  color: var(--color-text-muted);
}

.entry-modal__context strong {
  color: var(--color-text);
}

.entry-modal__error {
  margin: 0 0 12px;
  font-size: 0.85rem;
  color: var(--color-error-text);
}

.entry-form__grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

.entry-form__field--full {
  grid-column: 1 / -1;
}

@media (max-width: 480px) {
  .entry-form__grid {
    grid-template-columns: 1fr;
  }

  .entry-form__field--full {
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
