<script setup>
import { reactive, ref, onMounted } from 'vue'
import { transactionsApi, categoriesApi } from '@/services/api.js'
import { toTitleCase } from '@/utils/text.js'

const emit = defineEmits(['close', 'saved'])

const categories   = ref([])
const isLoadingCats  = ref(true)
const isSubmitting   = ref(false)
const errorMsg       = ref('')

const today = new Date().toISOString().slice(0, 10)

const form = reactive({
  type:        'saída',
  date:        today,
  amount:      '',
  description: '',
  category_id: '',
  origin:      'Manual',
  operation:   'Lançamento manual',
})

onMounted(async () => {
  try {
    const { data } = await categoriesApi.list()
    categories.value = data
    if (data.length) form.category_id = data[0].id
  } catch (err) {
    errorMsg.value = err.message
  } finally {
    isLoadingCats.value = false
  }
})

function onOverlay(e) {
  if (e.target === e.currentTarget) emit('close')
}

async function submit() {
  errorMsg.value = ''

  const amount = parseFloat(String(form.amount).replace(',', '.'))
  if (!form.date || !form.description.trim() || !form.category_id) {
    errorMsg.value = 'Preencha data, descrição, valor e categoria.'
    return
  }
  if (!Number.isFinite(amount) || amount <= 0) {
    errorMsg.value = 'Informe um valor maior que zero.'
    return
  }

  const description = toTitleCase(form.description.trim())

  isSubmitting.value = true
  try {
    const { data } = await transactionsApi.create({
      type:                   form.type,
      date:                   form.date,
      amount,
      category_id:            Number(form.category_id),
      raw_description:        description,
      translated_description: description,
      origin:                 form.origin.trim() || 'Manual',
      operation:              form.operation.trim() || 'Lançamento manual',
    })
    emit('saved', data)
  } catch (err) {
    errorMsg.value = err.message
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <Transition name="modal" appear>
    <div class="overlay" @click="onOverlay" role="dialog" aria-modal="true" aria-label="Nova transação manual">
      <div class="modal">
        <header class="modal-header">
          <h3 class="modal-title">
            <unicon name="plus" width="18" height="18" />
            Nova transação manual
          </h3>
          <button type="button" class="btn-close" aria-label="Fechar" :disabled="isSubmitting" @click="emit('close')">
            <unicon name="times" width="18" height="18" />
          </button>
        </header>

        <div class="modal-body">
          <div v-if="errorMsg" class="alert alert--error">
            <unicon name="times-circle" width="14" height="14" />
            {{ errorMsg }}
          </div>

          <div v-if="isLoadingCats" class="loading-line">Carregando categorias…</div>

          <form v-else @submit.prevent="submit" novalidate>
            <div class="form-row">
              <div class="form-group">
                <label class="form-label" for="manual-type">Tipo *</label>
                <select id="manual-type" v-model="form.type" class="form-control app-select" required>
                  <option value="entrada">Entrada</option>
                  <option value="saída">Saída</option>
                  <option value="rendimento">Rendimento</option>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label" for="manual-date">Data *</label>
                <input id="manual-date" v-model="form.date" type="date" class="form-control" required />
              </div>
            </div>

            <div class="form-group">
              <label class="form-label" for="manual-amount">Valor (R$) *</label>
              <input
                id="manual-amount"
                v-model="form.amount"
                type="number"
                class="form-control"
                min="0.01"
                step="0.01"
                placeholder="0,00"
                required
              />
            </div>

            <div class="form-group">
              <label class="form-label" for="manual-desc">Descrição *</label>
              <input
                id="manual-desc"
                v-model.trim="form.description"
                type="text"
                class="form-control"
                placeholder="Ex: Supermercado extra"
                maxlength="200"
                required
              />
            </div>

            <div class="form-group">
              <label class="form-label" for="manual-category">Categoria *</label>
              <select id="manual-category" v-model="form.category_id" class="form-control app-select" required>
                <option value="" disabled>Selecione…</option>
                <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                  {{ cat.name }}
                </option>
              </select>
            </div>

            <details class="form-advanced">
              <summary>Mais opções</summary>
              <div class="form-group">
                <label class="form-label" for="manual-origin">Origem</label>
                <input id="manual-origin" v-model.trim="form.origin" type="text" class="form-control" maxlength="80" />
              </div>
              <div class="form-group">
                <label class="form-label" for="manual-operation">Operação</label>
                <input id="manual-operation" v-model.trim="form.operation" type="text" class="form-control" maxlength="80" />
              </div>
            </details>

            <footer class="modal-footer">
              <button type="button" class="btn btn--outline" :disabled="isSubmitting" @click="emit('close')">
                Cancelar
              </button>
              <button type="submit" class="btn btn--primary" :disabled="isSubmitting">
                <span v-if="isSubmitting" class="spinner-ui spinner-ui--sm" aria-hidden="true"></span>
                {{ isSubmitting ? 'Salvando…' : 'Adicionar' }}
              </button>
            </footer>
          </form>
        </div>
      </div>
    </div>
  </Transition>
</template>

<style scoped>
.overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.6);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 500;
  padding: 20px;
  backdrop-filter: blur(2px);
}

.modal {
  background: var(--color-bg-secondary);
  border: 1px solid var(--color-border-dark);
  border-radius: var(--radius-lg);
  width: 100%;
  max-width: 480px;
  box-shadow: var(--shadow-md);
  overflow: hidden;
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 18px 24px 14px;
  border-bottom: 1px solid var(--color-border-dark);
}

.modal-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 1.05rem;
  font-weight: 700;
  margin: 0;
  color: var(--color-text);
}

.btn-close {
  background: none;
  border: none;
  color: var(--color-text-muted);
  cursor: pointer;
  padding: 4px;
  border-radius: var(--radius-sm);
  display: flex;
  align-items: center;
}

.btn-close:hover:not(:disabled) { background: var(--color-bg-elevated); color: var(--color-text); }
.btn-close:disabled { opacity: 0.5; }

.modal-body { padding: 20px 24px 24px; }

.alert {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 14px;
  border-radius: var(--radius-sm);
  font-size: 0.88rem;
  margin-bottom: 16px;
}

.alert--error {
  background: var(--color-error-bg);
  color: var(--color-error-text);
  border: 1px solid var(--color-error);
}

.loading-line { color: var(--color-text-muted); font-size: 0.9rem; }

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

@media (max-width: 480px) {
  .form-row { grid-template-columns: 1fr; }
}

.form-group { margin-bottom: 16px; }

.form-label {
  display: block;
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--color-text);
  margin-bottom: 6px;
}

.form-control {
  width: 100%;
  padding: 9px 12px;
  border: 1px solid var(--color-border-light);
  border-radius: var(--radius-sm);
  font-size: 0.9rem;
  color: var(--color-text);
  background: var(--color-bg-input);
  font-family: inherit;
}

.form-control:focus {
  outline: none;
  border-color: var(--color-accent);
  box-shadow: 0 0 0 2px rgba(197, 119, 0, 0.35);
}

.form-advanced {
  margin-bottom: 16px;
  font-size: 0.85rem;
  color: var(--color-text-muted);
}

.form-advanced summary {
  cursor: pointer;
  margin-bottom: 8px;
  color: var(--color-accent);
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  margin-top: 8px;
  padding-top: 16px;
  border-top: 1px solid var(--color-border-dark);
}

.btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 9px 18px;
  border-radius: var(--radius-sm);
  font-size: 0.88rem;
  font-weight: 600;
  border: none;
  cursor: pointer;
  font-family: inherit;
}

.btn--primary { background: var(--color-accent); color: var(--color-on-accent); }
.btn--outline { background: var(--color-bg-elevated); color: var(--color-text-muted); }
.btn:disabled { opacity: 0.55; cursor: not-allowed; }

.modal-enter-active,
.modal-leave-active { transition: opacity 0.2s ease; }
.modal-enter-from,
.modal-leave-to { opacity: 0; }
</style>
