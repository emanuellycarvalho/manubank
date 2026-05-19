<script setup>
import { reactive, ref, onMounted } from 'vue'
import { rulesApi, categoriesApi } from '@/services/api.js'

// ── Props / Emits ──────────────────────────────────────────────────────────
const props = defineProps({
  transaction: { type: Object, required: true },
})

const emit = defineEmits(['close', 'saved'])

// ── State ──────────────────────────────────────────────────────────────────
const categories  = ref([])
const isLoading   = ref(true)
const isSubmitting = ref(false)
const errorMsg    = ref('')

const form = reactive({
  substring:       '',
  translated_name: '',
  category_id:     '',
})

// ── Init ───────────────────────────────────────────────────────────────────
onMounted(async () => {
  // Pre-popula com a raw_description da transação
  form.substring       = props.transaction.raw_description ?? ''
  form.translated_name = props.transaction.translated_description ?? props.transaction.raw_description ?? ''

  try {
    const { data } = await categoriesApi.list()
    categories.value = data
    // Pré-seleciona a categoria atual se não for "Outros"
    if (props.transaction.category_name !== 'Outros') {
      form.category_id = props.transaction.category_id
    }
  } catch (err) {
    errorMsg.value = err.message
  } finally {
    isLoading.value = false
  }
})

// ── Submit ─────────────────────────────────────────────────────────────────
async function submit() {
  errorMsg.value = ''

  if (!form.substring.trim() || !form.translated_name.trim() || !form.category_id) {
    errorMsg.value = 'Preencha todos os campos.'
    return
  }

  isSubmitting.value = true

  try {
    const { data } = await rulesApi.create({
      category_id:     Number(form.category_id),
      substring:       form.substring.trim(),
      translated_name: form.translated_name.trim(),
    })
    emit('saved', data)
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
    <div class="overlay" @click="onOverlay" role="dialog" aria-modal="true" aria-label="Criar Regra">
      <div class="modal">
        <header class="modal-header">
          <h3 class="modal-title">
            <unicon name="tag-alt" width="18" height="18" />
            Criar Regra de Parsing
          </h3>
          <button class="btn-close" @click="$emit('close')" aria-label="Fechar">
            <unicon name="times" width="18" height="18" />
          </button>
        </header>

        <div class="modal-body">
          <!-- Transação de referência -->
          <div class="ref-box">
            <span class="ref-label">Transação original</span>
            <p class="ref-text">{{ transaction.raw_description }}</p>
            <span class="ref-meta">{{ transaction.date }} · {{ transaction.origin }}</span>
          </div>

          <div v-if="errorMsg" class="alert alert--error">
            <unicon name="times-circle" width="14" height="14" />
            {{ errorMsg }}
          </div>

          <div v-if="isLoading" class="loading-line">Carregando categorias…</div>

          <form v-else @submit.prevent="submit" novalidate>
            <div class="form-group">
              <label class="form-label" for="rule-substring">
                Substring de busca *
                <span class="form-hint">Texto que aparece na descrição bruta</span>
              </label>
              <input
                id="rule-substring"
                v-model="form.substring"
                type="text"
                class="form-control"
                placeholder="Ex: UBER TRIP"
                required
              />
            </div>

            <div class="form-group">
              <label class="form-label" for="rule-translated">
                Nome traduzido *
                <span class="form-hint">Como aparecerá no extrato</span>
              </label>
              <input
                id="rule-translated"
                v-model="form.translated_name"
                type="text"
                class="form-control"
                placeholder="Ex: Transporte"
                required
              />
            </div>

            <div class="form-group">
              <label class="form-label" for="rule-category">Categoria *</label>
              <select
                id="rule-category"
                v-model="form.category_id"
                class="form-control"
                required
              >
                <option value="" disabled>Selecione uma categoria</option>
                <option
                  v-for="cat in categories"
                  :key="cat.id"
                  :value="cat.id"
                >
                  {{ cat.name }} ({{ cat.type }})
                </option>
              </select>
            </div>

            <footer class="modal-footer">
              <button type="button" class="btn btn--outline" @click="$emit('close')" :disabled="isSubmitting">
                Cancelar
              </button>
              <button type="submit" class="btn btn--primary" :disabled="isSubmitting">
                <span v-if="isSubmitting" class="spinner" aria-hidden="true"></span>
                {{ isSubmitting ? 'Salvando…' : 'Criar Regra' }}
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
  background: rgba(0, 0, 0, .6);
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
  max-width: 500px;
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
  color: var(--color-text);
  margin: 0;
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
  transition: background .15s, color .15s;
}
.btn-close:hover { background: var(--color-bg-elevated); color: var(--color-text); }

.modal-body { padding: 20px 24px; }

.ref-box {
  background: var(--color-bg-elevated);
  border: 1px solid var(--color-border-dark);
  border-radius: var(--radius-sm);
  padding: 12px 16px;
  margin-bottom: 20px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.ref-label { font-size: .72rem; font-weight: 600; color: var(--color-text-subtle); text-transform: uppercase; letter-spacing: .4px; }
.ref-text  { font-size: .92rem; font-weight: 500; color: var(--color-text); font-family: monospace; word-break: break-all; }
.ref-meta  { font-size: .78rem; color: var(--color-text-muted); }

.alert { padding: 10px 14px; border-radius: var(--radius-sm); font-size: .88rem; margin-bottom: 16px; }
.alert--error { background: var(--color-error-bg); color: var(--color-error-text); border: 1px solid var(--color-error); }

.form-group { margin-bottom: 16px; }

.form-label {
  display: flex;
  flex-direction: column;
  gap: 2px;
  font-size: .85rem;
  font-weight: 600;
  color: var(--color-text-muted);
  margin-bottom: 6px;
}

.form-hint { font-size: .75rem; font-weight: 400; color: var(--color-text-subtle); }

.form-control {
  width: 100%;
  padding: 9px 12px;
  border: 1px solid var(--color-border-light);
  border-radius: var(--radius-sm);
  font-size: .9rem;
  color: var(--color-text);
  background: var(--color-bg-input);
  transition: border-color .15s, box-shadow .15s;
  outline: none;
  font-family: inherit;
}

.form-control:focus {
  border-color: var(--color-accent);
  box-shadow: 0 0 0 3px rgba(242, 76, 0, 0.25);
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  margin-top: 20px;
  padding-top: 16px;
  border-top: 1px solid var(--color-border-dark);
}

.btn--primary { background: var(--color-accent); color: var(--color-bg-primary); border-color: var(--color-accent); }

.spinner {
  display: inline-block;
  width: 14px;
  height: 14px;
  border: 2px solid var(--color-border-light);
  border-top-color: var(--color-accent);
  border-radius: 50%;
  animation: spin .7s linear infinite;
}

@keyframes spin { to { transform: rotate(360deg); } }

.loading-line { color: var(--color-text-muted); font-size: .9rem; padding: 20px 0; text-align: center; }

/* Transition */
.modal-enter-active, .modal-leave-active { transition: opacity .2s ease; }
.modal-enter-active .modal, .modal-leave-active .modal { transition: transform .2s ease, opacity .2s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.modal-enter-from .modal, .modal-leave-to .modal { transform: scale(.95) translateY(-10px); opacity: 0; }
</style>
