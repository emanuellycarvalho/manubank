<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { rulesApi, categoriesApi } from '@/services/api.js'
import { useTableSort } from '@/composables/useTableSort.js'
import ConfirmModal from '@/components/ConfirmModal.vue'

const RULE_SORT_COLS = [
  { key: 'id',               getValue: (r) => r.id },
  { key: 'substring',        getValue: (r) => r.substring },
  { key: 'translated_name',  getValue: (r) => r.translated_name },
  { key: 'category_name',    getValue: (r) => r.category_name },
]

const rules        = ref([])
const categories   = ref([])
const isLoading    = ref(false)
const errorMsg     = ref('')
const successMsg   = ref('')

const showModal      = ref(false)
const isSubmitting   = ref(false)
const isEditing      = ref(false)
const ruleToDeactivate = ref(null)
const isDeactivating   = ref(false)

const EMPTY_FORM = () => ({
  id:              null,
  substring:       '',
  translated_name: '',
  category_id:     '',
})
const form = reactive(EMPTY_FORM())

const { sortedItems: sortedRules, toggleSort, sortClass } = useTableSort(
  rules,
  RULE_SORT_COLS,
  { key: 'substring', dir: 'asc' },
)

const modalTitle = computed(() => (isEditing.value ? 'Editar Regra' : 'Nova Regra'))

async function loadRules() {
  isLoading.value = true
  errorMsg.value  = ''
  try {
    const { data } = await rulesApi.list()
    rules.value = data
  } catch (err) {
    errorMsg.value = err.message
  } finally {
    isLoading.value = false
  }
}

async function loadCategories() {
  try {
    const { data } = await categoriesApi.list()
    categories.value = data
  } catch {
    /* modal mostrará erro se necessário */
  }
}

async function submitForm() {
  if (!form.substring.trim() || !form.translated_name.trim() || !form.category_id) {
    errorMsg.value = 'Preencha substring, nome traduzido e categoria.'
    return
  }

  isSubmitting.value = true
  errorMsg.value     = ''

  const payload = {
    category_id:     Number(form.category_id),
    substring:       form.substring.trim(),
    translated_name: form.translated_name.trim(),
  }

  try {
    if (isEditing.value) {
      await rulesApi.update(form.id, payload)
      showSuccess('Regra atualizada com sucesso!')
    } else {
      const { data } = await rulesApi.create(payload)
      const n = data?.transactions_updated ?? 0
      const existed = data?.already_existed ?? false
      const extra = n > 0
        ? ` ${n} transaç${n === 1 ? 'ão' : 'ões'} em "Outros" atualizadas.`
        : ''
      showSuccess(existed
        ? `Regra já existia — aplicada retroativamente.${extra}`
        : `Regra criada!${extra}`)
    }

    closeModal()
    await loadRules()
  } catch (err) {
    errorMsg.value = err.message
  } finally {
    isSubmitting.value = false
  }
}

function requestDeactivate(rule) {
  ruleToDeactivate.value = rule
}

function cancelDeactivate() {
  if (!isDeactivating.value) ruleToDeactivate.value = null
}

async function confirmDeactivate() {
  if (!ruleToDeactivate.value) return
  isDeactivating.value = true
  const label = ruleToDeactivate.value.translated_name
  try {
    await rulesApi.remove(ruleToDeactivate.value.id)
    ruleToDeactivate.value = null
    showSuccess(`Regra "${label}" desativada.`)
    await loadRules()
  } catch (err) {
    errorMsg.value = err.message
  } finally {
    isDeactivating.value = false
  }
}

function openCreateModal() {
  Object.assign(form, EMPTY_FORM())
  if (categories.value.length) {
    form.category_id = categories.value[0].id
  }
  isEditing.value = false
  showModal.value = true
  errorMsg.value  = ''
}

function openEditModal(rule) {
  Object.assign(form, {
    id:              rule.id,
    substring:       rule.substring,
    translated_name: rule.translated_name,
    category_id:     rule.category_id,
  })
  isEditing.value = true
  showModal.value = true
  errorMsg.value  = ''
}

function closeModal() {
  showModal.value = false
}

function onOverlayClick(e) {
  if (e.target === e.currentTarget) closeModal()
}

let successTimer = null
function showSuccess(msg) {
  successMsg.value = msg
  clearTimeout(successTimer)
  successTimer = setTimeout(() => { successMsg.value = '' }, 4500)
}

onMounted(async () => {
  await Promise.all([loadRules(), loadCategories()])
})
</script>

<template>
  <div class="page">
    <header class="page-header">
      <div>
        <h2 class="page-title">Regras de parsing</h2>
        <p class="page-subtitle">
          Mapeie textos do extrato para nomes traduzidos e categorias
        </p>
      </div>
      <button class="btn btn--primary" @click="openCreateModal">
        <unicon name="plus" width="16" height="16" />
        Nova Regra
      </button>
    </header>

    <div v-if="successMsg" class="alert alert--success" role="alert">
      <unicon name="check-circle" width="16" height="16" />
      {{ successMsg }}
    </div>
    <div v-if="errorMsg && !showModal" class="alert alert--error" role="alert">
      <unicon name="times-circle" width="16" height="16" />
      {{ errorMsg }}
    </div>

    <div v-if="isLoading" class="loading-state">
      <span class="spinner" aria-hidden="true"></span>
      Carregando regras…
    </div>

    <div v-else-if="!rules.length && !isLoading" class="empty-state">
      <unicon name="clipboard-alt" width="48" height="48" class="empty-icon" />
      <p>Nenhuma regra activa. Crie a primeira ou use o extrato para gerar regras a partir de transações.</p>
    </div>

    <div v-else class="card">
      <table class="table">
        <thead>
          <tr>
            <th class="th-sortable" :class="sortClass('id')" @click="toggleSort('id')">#</th>
            <th class="th-sortable" :class="sortClass('substring')" @click="toggleSort('substring')">
              Substring
            </th>
            <th class="th-sortable" :class="sortClass('translated_name')" @click="toggleSort('translated_name')">
              Nome traduzido
            </th>
            <th class="th-sortable" :class="sortClass('category_name')" @click="toggleSort('category_name')">
              Categoria
            </th>
            <th class="col-actions">Ações</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="rule in sortedRules" :key="rule.id">
            <td class="col-id">{{ rule.id }}</td>
            <td class="col-substring">
              <code class="substring-code">{{ rule.substring }}</code>
            </td>
            <td class="col-name">{{ rule.translated_name }}</td>
            <td>
              <div class="category-cell">
                <span class="color-dot" :style="{ background: rule.category_color }"></span>
                {{ rule.category_name }}
              </div>
            </td>
            <td class="col-actions">
              <div class="col-actions__inner">
                <button
                  class="btn btn--sm btn--outline"
                  title="Editar"
                  @click="openEditModal(rule)"
                >
                  <unicon name="edit-alt" width="13" height="13" />
                  Editar
                </button>
                <button
                  class="btn btn--sm btn--danger"
                  title="Desativar"
                  @click="requestDeactivate(rule)"
                >
                  <unicon name="ban" width="13" height="13" />
                  Desativar
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <Transition name="modal">
      <div
        v-if="showModal"
        class="modal-overlay"
        @click="onOverlayClick"
        role="dialog"
        aria-modal="true"
        :aria-label="modalTitle"
      >
        <div class="modal">
          <header class="modal-header">
            <h3 class="modal-title">{{ modalTitle }}</h3>
            <button class="modal-close" @click="closeModal" aria-label="Fechar">
              <unicon name="times" width="18" height="18" />
            </button>
          </header>

          <div class="modal-body">
            <div v-if="errorMsg" class="alert alert--error alert--sm" role="alert">
              <unicon name="times-circle" width="14" height="14" />
              {{ errorMsg }}
            </div>

            <form @submit.prevent="submitForm" novalidate>
              <div class="form-group">
                <label class="form-label" for="rule-substring">
                  Substring de busca *
                  <span class="form-hint">Texto que aparece na descrição bruta (sem distinção de maiúsculas)</span>
                </label>
                <input
                  id="rule-substring"
                  v-model.trim="form.substring"
                  type="text"
                  class="form-control"
                  placeholder="Ex: uber, supermercado"
                  maxlength="200"
                  required
                  autofocus
                />
              </div>

              <div class="form-group">
                <label class="form-label" for="rule-translated">Nome traduzido *</label>
                <input
                  id="rule-translated"
                  v-model.trim="form.translated_name"
                  type="text"
                  class="form-control"
                  placeholder="Ex: Transporte"
                  maxlength="120"
                  required
                />
              </div>

              <div class="form-group">
                <label class="form-label" for="rule-category">Categoria *</label>
                <select
                  id="rule-category"
                  v-model="form.category_id"
                  class="form-control app-select"
                  required
                >
                  <option value="" disabled>Selecione…</option>
                  <option
                    v-for="cat in categories"
                    :key="cat.id"
                    :value="cat.id"
                  >
                    {{ cat.name }}
                  </option>
                </select>
              </div>

              <footer class="modal-footer">
                <button
                  type="button"
                  class="btn btn--outline"
                  :disabled="isSubmitting"
                  @click="closeModal"
                >
                  Cancelar
                </button>
                <button type="submit" class="btn btn--primary" :disabled="isSubmitting">
                  <span v-if="isSubmitting" class="spinner spinner--sm" aria-hidden="true"></span>
                  {{ isSubmitting ? 'Salvando…' : (isEditing ? 'Salvar alterações' : 'Criar regra') }}
                </button>
              </footer>
            </form>
          </div>
        </div>
      </div>
    </Transition>

    <ConfirmModal
      v-if="ruleToDeactivate"
      title="Desativar regra?"
      message="A regra deixará de ser aplicada em novas importações. Transações já categorizadas não são alteradas."
      :detail="`${ruleToDeactivate.substring} → ${ruleToDeactivate.translated_name}`"
      confirm-label="Desativar"
      cancel-label="Cancelar"
      :loading="isDeactivating"
      @close="cancelDeactivate"
      @confirm="confirmDeactivate"
    />
  </div>
</template>

<style scoped>
.page { max-width: 1100px; }

.page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 24px;
  gap: 16px;
  flex-wrap: wrap;
}

.alert {
  padding: 10px 14px;
  border-radius: var(--radius-sm);
  font-size: .88rem;
  margin-bottom: 16px;
  display: flex;
  align-items: center;
  gap: 8px;
}
.alert--success { background: var(--color-success-bg); color: var(--color-success-text); border: 1px solid var(--color-success); }
.alert--error   { background: var(--color-error-bg); color: var(--color-error-text); border: 1px solid var(--color-error); }
.alert--sm { margin-bottom: 20px; }
.alert :deep(svg) { fill: currentColor; flex-shrink: 0; }

.loading-state,
.empty-state {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  padding: 60px 20px;
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm);
  color: var(--color-text-muted);
  font-size: .95rem;
}
.empty-state { flex-direction: column; gap: 14px; text-align: center; max-width: 420px; margin: 0 auto; }
.empty-icon { opacity: 0.85; }

.spinner {
  display: inline-block;
  width: 18px;
  height: 18px;
  border: 2px solid var(--color-border-light);
  border-top-color: var(--color-accent);
  border-radius: 50%;
  animation: spin .7s linear infinite;
  vertical-align: middle;
}
.spinner--sm { width: 14px; height: 14px; }
@keyframes spin { to { transform: rotate(360deg); } }

.card {
  background: var(--color-surface);
  border: 1px solid var(--color-border-dark);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm);
  overflow: hidden;
}

.table { width: 100%; border-collapse: collapse; font-size: .9rem; }
.table th,
.table td {
  padding: 12px 16px;
  text-align: left;
  border-bottom: 1px solid var(--color-border-subtle-dark);
  color: var(--color-text);
}
.table th {
  background: var(--color-surface);
  font-weight: 600;
  color: var(--color-text);
  font-size: .78rem;
  text-transform: uppercase;
  letter-spacing: .5px;
}
.table tbody tr:last-child td { border-bottom: none; }
.table tbody tr:hover { background: rgba(255, 255, 255, 0.03); }

.col-id { width: 48px; color: var(--color-text-subtle); }
.col-substring { max-width: 280px; }
.substring-code {
  font-size: .82rem;
  font-family: ui-monospace, monospace;
  word-break: break-word;
}
.col-name { font-weight: 500; }
.category-cell { display: flex; align-items: center; gap: 8px; }
.color-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }

.col-actions { width: 280px; min-width: 280px; }
.col-actions__inner {
  display: flex;
  gap: 8px;
  align-items: center;
  flex-wrap: nowrap;
}

.btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  border-radius: var(--radius-sm);
  font-size: .88rem;
  font-weight: 600;
  cursor: pointer;
  border: none;
  font-family: inherit;
  transition: filter .15s, opacity .15s;
  white-space: nowrap;
}
.btn :deep(svg) { fill: currentColor; }
.btn:disabled { opacity: .6; cursor: not-allowed; }
.btn:not(:disabled):hover { filter: brightness(1.1); }
.btn--primary { background: var(--color-accent); color: var(--color-on-accent); }
.btn--outline { background: var(--color-bg-elevated); color: var(--color-text-muted); }
.btn--danger  { background: var(--color-error); color: var(--color-text); }
.btn--sm { padding: 5px 10px; font-size: .8rem; }

.modal-overlay {
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
  background: var(--color-surface);
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
.modal-title { font-size: 1.05rem; font-weight: 700; color: var(--color-text); margin: 0; }
.modal-close {
  background: none;
  border: none;
  color: var(--color-text-muted);
  cursor: pointer;
  padding: 4px;
  border-radius: var(--radius-sm);
  display: flex;
  align-items: center;
}
.modal-close:hover { background: var(--color-bg-elevated); color: var(--color-text); }
.modal-body { padding: 20px 24px 24px; }

.form-group { margin-bottom: 18px; }
.form-label {
  display: block;
  font-size: .85rem;
  font-weight: 600;
  color: var(--color-text);
  margin-bottom: 6px;
}
.form-hint {
  display: block;
  font-weight: 400;
  font-size: .78rem;
  color: var(--color-text-muted);
  margin-top: 2px;
}
.form-control {
  width: 100%;
  padding: 9px 12px;
  border: 1px solid var(--color-border-light);
  border-radius: var(--radius-sm);
  font-size: .9rem;
  color: var(--color-text);
  background: var(--color-bg-input);
  font-family: inherit;
  outline: none;
}
.form-control:focus {
  border-color: var(--color-accent);
  box-shadow: 0 0 0 2px rgba(197, 119, 0, 0.35);
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  margin-top: 24px;
  padding-top: 16px;
  border-top: 1px solid var(--color-border-dark);
}

.modal-enter-active,
.modal-leave-active { transition: opacity .2s ease; }
.modal-enter-from,
.modal-leave-to { opacity: 0; }
</style>
