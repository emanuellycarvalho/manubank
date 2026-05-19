<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { categoriesApi } from '@/services/api.js'

// ── State ──────────────────────────────────────────────────────────────────
const categories  = ref([])
const isLoading   = ref(false)
const errorMsg    = ref('')
const successMsg  = ref('')

const showModal   = ref(false)
const isSubmitting = ref(false)
const isEditing   = ref(false)

const EMPTY_FORM = () => ({ id: null, name: '', type: 'Fixo', color: '#253762' })
const form = reactive(EMPTY_FORM())

const TYPE_LABELS = {
  Fixo:      { label: 'Fixo',     cls: 'badge--fixo'     },
  Variável:  { label: 'Variável', cls: 'badge--variavel'  },
  Neutro:    { label: 'Neutro',   cls: 'badge--neutro'    },
}

// ── Computed ───────────────────────────────────────────────────────────────
const modalTitle = computed(() => isEditing.value ? 'Editar Categoria' : 'Nova Categoria')

// ── API calls ──────────────────────────────────────────────────────────────
async function loadCategories() {
  isLoading.value = true
  errorMsg.value  = ''
  try {
    const { data } = await categoriesApi.list()
    categories.value = data
  } catch (err) {
    errorMsg.value = err.message
  } finally {
    isLoading.value = false
  }
}

async function submitForm() {
  if (!form.name.trim()) {
    errorMsg.value = 'O nome é obrigatório.'
    return
  }

  isSubmitting.value = true
  errorMsg.value     = ''

  try {
    const payload = { name: form.name.trim(), type: form.type, color: form.color }

    if (isEditing.value) {
      await categoriesApi.update(form.id, payload)
      showSuccess('Categoria atualizada com sucesso!')
    } else {
      await categoriesApi.create(payload)
      showSuccess('Categoria criada com sucesso!')
    }

    closeModal()
    await loadCategories()
  } catch (err) {
    errorMsg.value = err.message
  } finally {
    isSubmitting.value = false
  }
}

async function deactivate(category) {
  if (!confirm(`Desativar "${category.name}"? Ela não aparecerá mais nas listagens.`)) return

  errorMsg.value = ''
  try {
    await categoriesApi.remove(category.id)
    showSuccess(`"${category.name}" desativada.`)
    await loadCategories()
  } catch (err) {
    errorMsg.value = err.message
  }
}

// ── Modal helpers ──────────────────────────────────────────────────────────
function openCreateModal() {
  Object.assign(form, EMPTY_FORM())
  isEditing.value = false
  showModal.value = true
  errorMsg.value  = ''
}

function openEditModal(category) {
  Object.assign(form, {
    id:    category.id,
    name:  category.name,
    type:  category.type,
    color: category.color,
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

// ── Feedback ───────────────────────────────────────────────────────────────
let successTimer = null
function showSuccess(msg) {
  successMsg.value = msg
  clearTimeout(successTimer)
  successTimer = setTimeout(() => { successMsg.value = '' }, 3500)
}

// ── Lifecycle ─────────────────────────────────────────────────────────────
onMounted(loadCategories)
</script>

<template>
  <div class="page">
    <!-- Page header -->
    <header class="page-header">
      <div>
        <h2 class="page-title">Categorias</h2>
        <p class="page-subtitle">Gerencie as categorias das suas transações</p>
      </div>
      <button class="btn btn--primary" @click="openCreateModal">
        + Nova Categoria
      </button>
    </header>

    <!-- Alerts -->
    <div v-if="successMsg" class="alert alert--success" role="alert">
      <unicon name="check-circle" width="16" height="16" />
      {{ successMsg }}
    </div>
    <div v-if="errorMsg && !showModal" class="alert alert--error" role="alert">
      <unicon name="times-circle" width="16" height="16" />
      {{ errorMsg }}
    </div>

    <!-- Loading -->
    <div v-if="isLoading" class="loading-state">
      <span class="spinner" aria-hidden="true"></span>
      Carregando categorias…
    </div>

    <!-- Empty state -->
    <div v-else-if="!categories.length && !isLoading" class="empty-state">
      <unicon name="tag-alt" width="48" height="48" class="empty-icon" />
      <p>Nenhuma categoria activa. Crie a primeira!</p>
    </div>

    <!-- Table -->
    <div v-else class="card">
      <table class="table">
        <thead>
          <tr>
            <th>#</th>
            <th>Nome</th>
            <th>Tipo</th>
            <th>Cor</th>
            <th class="col-actions">Ações</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="cat in categories" :key="cat.id">
            <td class="col-id">{{ cat.id }}</td>
            <td class="col-name">{{ cat.name }}</td>
            <td>
              <span
                class="badge"
                :class="TYPE_LABELS[cat.type]?.cls"
              >
                {{ TYPE_LABELS[cat.type]?.label ?? cat.type }}
              </span>
            </td>
            <td>
              <div class="color-cell">
                <span
                  class="color-swatch"
                  :style="{ background: cat.color }"
                  :title="cat.color"
                ></span>
                <code class="color-hex">{{ cat.color }}</code>
              </div>
            </td>
            <td class="col-actions">
              <button
                class="btn btn--sm btn--outline"
                @click="openEditModal(cat)"
                title="Editar"
              >
                <unicon name="edit-alt" width="13" height="13" />
                Editar
              </button>
              <button
                class="btn btn--sm btn--danger"
                @click="deactivate(cat)"
                title="Desativar"
              >
                <unicon name="ban" width="13" height="13" />
                Desativar
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- ── Modal ── -->
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
            <!-- Error inside modal -->
            <div v-if="errorMsg" class="alert alert--error alert--sm" role="alert">
              <unicon name="times-circle" width="14" height="14" />
              {{ errorMsg }}
            </div>

            <form @submit.prevent="submitForm" novalidate>
              <!-- Name -->
              <div class="form-group">
                <label class="form-label" for="cat-name">Nome *</label>
                <input
                  id="cat-name"
                  v-model.trim="form.name"
                  type="text"
                  class="form-control"
                  placeholder="Ex: Alimentação"
                  maxlength="80"
                  required
                  autofocus
                />
              </div>

              <!-- Type -->
              <div class="form-group">
                <label class="form-label" for="cat-type">Tipo *</label>
                <select id="cat-type" v-model="form.type" class="form-control app-select">
                  <option value="Fixo">Fixo</option>
                  <option value="Variável">Variável</option>
                  <option value="Neutro">Neutro</option>
                </select>
              </div>

              <!-- Color -->
              <div class="form-group">
                <label class="form-label" for="cat-color">Cor</label>
                <div class="color-picker-row">
                  <input
                    id="cat-color"
                    v-model="form.color"
                    type="color"
                    class="color-input"
                  />
                  <span class="color-preview-label">{{ form.color }}</span>
                  <span
                    class="color-swatch color-swatch--lg"
                    :style="{ background: form.color }"
                  ></span>
                </div>
              </div>

              <footer class="modal-footer">
                <button
                  type="button"
                  class="btn btn--outline"
                  @click="closeModal"
                  :disabled="isSubmitting"
                >
                  Cancelar
                </button>
                <button
                  type="submit"
                  class="btn btn--primary"
                  :disabled="isSubmitting"
                >
                  <span v-if="isSubmitting" class="spinner spinner--sm" aria-hidden="true"></span>
                  {{ isSubmitting ? 'Salvando…' : (isEditing ? 'Salvar Alterações' : 'Criar Categoria') }}
                </button>
              </footer>
            </form>
          </div>
        </div>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.page { max-width: 960px; }

.page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 24px; gap: 16px; }
.page-title  { font-size: 1.6rem; font-weight: 700; color: var(--color-text); }
.page-subtitle { margin-top: 4px; color: var(--color-text-muted); font-size: .9rem; }

/* ── Alerts ── */
.alert { padding: 10px 14px; border-radius: var(--radius-sm); font-size: .88rem; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
.alert--success { background: var(--color-success-bg); color: var(--color-success-text); border: 1px solid var(--color-success); }
.alert--error   { background: var(--color-error-bg); color: var(--color-error-text); border: 1px solid var(--color-error); }
.alert--sm { margin-bottom: 20px; }
.alert :deep(svg) { fill: currentColor; flex-shrink: 0; }

/* ── Loading / Empty ── */
.loading-state, .empty-state {
  display: flex; align-items: center; justify-content: center; gap: 10px;
  padding: 60px 20px;
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm);
  color: var(--color-text-muted);
  font-size: .95rem;
}
.empty-state { flex-direction: column; gap: 14px; }
.empty-icon { fill: var(--color-text-subtle); }

/* ── Spinner ── */
.spinner {
  display: inline-block; width: 18px; height: 18px;
  border: 2px solid var(--color-border-light); border-top-color: var(--color-accent);
  border-radius: 50%; animation: spin .7s linear infinite; vertical-align: middle;
}
.spinner--sm { width: 14px; height: 14px; }
@keyframes spin { to { transform: rotate(360deg); } }

/* ── Card / Table ── */
.card {
  background: var(--color-surface);
  border: 1px solid var(--color-border-light);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm);
  overflow: hidden;
}

.table { width: 100%; border-collapse: collapse; font-size: .9rem; }
.table th, .table td { padding: 12px 16px; text-align: left; border-bottom: 1px solid var(--color-border-subtle-dark); color: var(--color-text); }
.table th { background: var(--color-surface-elevated); font-weight: 600; color: var(--color-text-muted); font-size: .78rem; text-transform: uppercase; letter-spacing: .5px; }
.table tbody tr:last-child td { border-bottom: none; }
.table tbody tr:hover { background: var(--color-surface-elevated); }

.col-id     { width: 48px; color: var(--color-text-subtle); }
.col-name   { font-weight: 500; }
.col-actions { width: 200px; display: flex; gap: 8px; align-items: center; }

/* ── Badges ── */
.badge { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: .78rem; font-weight: 600; letter-spacing: .3px; }
.badge--fixo     { background: var(--color-surface-elevated); color: var(--color-accent-secondary); border: 1px solid var(--color-accent-secondary); }
.badge--variavel { background: var(--color-warning-bg); color: var(--color-warning); }
.badge--neutro   { background: var(--color-surface-elevated); color: var(--color-text-muted); }

/* ── Color cell ── */
.color-cell { display: flex; align-items: center; gap: 8px; }
.color-swatch { width: 20px; height: 20px; border-radius: 4px; border: 1px solid var(--color-border-light); flex-shrink: 0; }
.color-swatch--lg { width: 28px; height: 28px; border-radius: 6px; }
.color-hex { font-size: .8rem; color: var(--color-text-muted); font-family: monospace; }

/* ── Buttons ── */
.btn {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 8px 16px; border-radius: var(--radius-sm);
  font-size: .88rem; font-weight: 600; cursor: pointer; border: none;
  font-family: inherit;
  transition: filter .15s, opacity .15s;
  white-space: nowrap;
}
.btn :deep(svg) { fill: currentColor; }
.btn:disabled { opacity: .6; cursor: not-allowed; }
.btn:not(:disabled):hover { filter: brightness(1.1); }
.btn--primary { background: var(--color-accent); color: var(--color-on-accent); }
.btn--outline { background: transparent; border: 1.5px solid var(--color-border-light); color: var(--color-text-muted); }
.btn--danger  { background: var(--color-error); color: var(--color-text); }
.btn--sm { padding: 5px 10px; font-size: .8rem; }

/* ── Modal ── */
.modal-overlay {
  position: fixed; inset: 0;
  background: rgba(0, 0, 0, .6);
  display: flex; align-items: center; justify-content: center;
  z-index: 500; padding: 20px;
  backdrop-filter: blur(2px);
}

.modal {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  width: 100%; max-width: 460px;
  box-shadow: var(--shadow-md);
  overflow: hidden;
  border: 1px solid var(--color-border-dark);
}

.modal-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 20px 24px 16px;
  border-bottom: 1px solid var(--color-border-dark);
}

.modal-title { font-size: 1.1rem; font-weight: 700; color: var(--color-text); }

.modal-close {
  background: none; border: none;
  color: var(--color-text-subtle);
  cursor: pointer; padding: 4px;
  border-radius: var(--radius-sm);
  display: flex; align-items: center;
  transition: background .15s, color .15s;
}
.modal-close :deep(svg) { fill: currentColor; }
.modal-close:hover { background: var(--color-surface-elevated); color: var(--color-text); }

.modal-body { padding: 20px 24px; }

/* ── Form ── */
.form-group { margin-bottom: 18px; }
.form-label { display: block; font-size: .85rem; font-weight: 600; color: var(--color-text-muted); margin-bottom: 6px; }

.form-control {
  width: 100%; padding: 9px 12px;
  border: 1px solid var(--color-border-light);
  border-radius: var(--radius-sm);
  font-size: .92rem;
  color: var(--color-text);
  background: var(--color-surface-input);
  transition: border-color .15s, box-shadow .15s;
  outline: none;
  font-family: inherit;
}
.form-control:focus { border-color: var(--color-accent); box-shadow: 0 0 0 3px rgba(249,168,38,.15); }

/* ── Color picker row ── */
.color-picker-row { display: flex; align-items: center; gap: 12px; }
.color-input {
  width: 48px; height: 38px; padding: 2px;
  border: 1px solid var(--color-border-light);
  border-radius: var(--radius-sm);
  cursor: pointer; background: none;
}
.color-input::-webkit-color-swatch-wrapper { padding: 0; border-radius: 4px; }
.color-input::-webkit-color-swatch         { border: none; border-radius: 4px; }
.color-preview-label { font-family: monospace; font-size: .88rem; color: var(--color-text-muted); min-width: 70px; }

/* ── Modal footer ── */
.modal-footer { display: flex; justify-content: flex-end; gap: 10px; margin-top: 24px; padding-top: 16px; border-top: 1px solid var(--color-border-dark); }

/* ── Transitions ── */
.modal-enter-active, .modal-leave-active { transition: opacity .2s ease; }
.modal-enter-active .modal, .modal-leave-active .modal { transition: transform .2s ease, opacity .2s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.modal-enter-from .modal, .modal-leave-to .modal { transform: scale(.95) translateY(-12px); opacity: 0; }
</style>
