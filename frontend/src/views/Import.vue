<script setup>
import { ref, computed } from 'vue'
import { importApi } from '@/services/api.js'

const mode = ref('file')

const selectedFile = ref(null)
const dragOver     = ref(false)
const fileInputRef = ref(null)

const pasteText = ref('')
const pasteYear = ref(new Date().getFullYear())

const isLoading = ref(false)
const result    = ref(null)

const ACCEPT_ATTR = 'application/pdf,.pdf,text/csv,.csv'

function pickFile(file) {
  if (!file) return
  result.value       = null
  selectedFile.value = file
}

function onFileInput(e)  { pickFile(e.target.files?.[0] ?? null) }
function onDrop(e)        { dragOver.value = false; pickFile(e.dataTransfer?.files?.[0] ?? null) }

function clearFile() {
  selectedFile.value = null
  result.value       = null
  if (fileInputRef.value) fileInputRef.value.value = ''
}

function formatBytes(bytes) {
  if (bytes < 1024)        return `${bytes} B`
  if (bytes < 1048576)     return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / 1048576).toFixed(1)} MB`
}

function switchMode(m) {
  mode.value   = m
  result.value = null
}

const canSubmit = computed(() =>
  mode.value === 'file' ? !!selectedFile.value : pasteText.value.trim().length > 0
)

async function submit() {
  if (!canSubmit.value || isLoading.value) return
  isLoading.value = true
  result.value    = null

  try {
    if (mode.value === 'file') {
      const { data } = await importApi.upload(selectedFile.value)
      result.value = data
      if (data.success) prepareForNextImport()
    } else {
      const { data } = await importApi.importText(pasteText.value.trim(), pasteYear.value)
      result.value = data
      if (data.success) prepareForNextImport()
    }
  } catch (err) {
    result.value = { success: false, error: err.message }
  } finally {
    isLoading.value = false
  }
}

function prepareForNextImport() {
  selectedFile.value = null
  pasteText.value    = ''
  if (fileInputRef.value) fileInputRef.value.value = ''
}

function reset() {
  clearFile()
  pasteText.value = ''
  result.value    = null
}
</script>

<template>
  <div class="page">
    <header class="page-header">
      <div>
        <h2 class="page-title">Importar Extrato</h2>
        <p class="page-subtitle">Envie um ficheiro ou cole o texto da fatura Nubank</p>
      </div>
    </header>

    <div class="import-layout">
      <!-- Esquerda: resultado ou instruções -->
      <aside class="panel panel--result">
        <div v-if="isLoading" class="panel-placeholder panel-placeholder--loading">
          <span class="spinner spinner--muted" aria-hidden="true"></span>
          <p>A importar transações…</p>
        </div>

        <div v-else-if="result?.success" class="result-card result-card--success">
          <div class="result-card__header">
            <span class="result-card__icon"><unicon name="check-circle" width="22" height="22" /></span>
            <h3>Importação concluída</h3>
          </div>
          <div class="result-stats">
            <div class="stat">
              <span class="stat__value">{{ result.imported }}</span>
              <span class="stat__label">importadas</span>
            </div>
            <div class="stat">
              <span class="stat__value stat__value--muted">{{ result.skipped }}</span>
              <span class="stat__label">ignoradas</span>
            </div>
          </div>

          <div v-if="Object.keys(result.month_year_groups ?? {}).length" class="months-list">
            <p class="months-list__title">Meses processados:</p>
            <ul>
              <li v-for="(count, month) in result.month_year_groups" :key="month">
                <strong>{{ month }}</strong> — {{ count }} transaç{{ count === 1 ? 'ão' : 'ões' }}
              </li>
            </ul>
          </div>

          <RouterLink to="/extrato" class="result-link">Ver no extrato →</RouterLink>
        </div>

        <div v-else-if="result && !result.success" class="result-card result-card--error">
          <div class="result-card__header">
            <span class="result-card__icon"><unicon name="times-circle" width="22" height="22" /></span>
            <h3>Falha na importação</h3>
          </div>
          <p class="error-message">{{ result.error }}</p>
          <button class="btn btn--outline btn--sm mt" @click="reset">
            Limpar e tentar novamente
          </button>
        </div>

        <div v-else class="instructions">
          <h3 class="instructions__title">Como importar</h3>
          <ol class="instructions__list">
            <li>
              <strong>Ficheiro (à direita)</strong> — arraste ou selecione um PDF da fatura Nubank
              ou um CSV do Mercado Pago.
            </li>
            <li>
              <strong>Colar texto</strong> — use o separador «Colar texto» para linhas copiadas
              da fatura de crédito Nubank (data, cartão e valor).
            </li>
            <li>
              Clique em <strong>Importar</strong>. As transações são categorizadas pelas regras
              definidas no sistema.
            </li>
            <li>
              Duplicados são ignorados — pode reimportar o mesmo ficheiro sem criar lançamentos
              repetidos.
            </li>
          </ol>
          <p class="instructions__footer">
            O resumo aparece neste painel. Depois consulte o
            <RouterLink to="/extrato">extrato</RouterLink> para rever ou ajustar categorias.
          </p>
        </div>
      </aside>

      <!-- Direita: formulário -->
      <section class="panel panel--import">
        <div class="tabs">
          <button
            class="tab"
            :class="{ 'tab--active': mode === 'file' }"
            @click="switchMode('file')"
          >
            <unicon name="folder-upload" width="16" height="16" />
            Enviar ficheiro
          </button>
          <button
            class="tab"
            :class="{ 'tab--active': mode === 'text' }"
            @click="switchMode('text')"
          >
            <unicon name="clipboard-alt" width="16" height="16" />
            Colar texto (Nubank)
          </button>
        </div>

        <template v-if="mode === 'file'">
          <div
            class="dropzone"
            :class="{ 'dropzone--over': dragOver, 'dropzone--ready': !!selectedFile }"
            @dragover.prevent="dragOver = true"
            @dragleave.prevent="dragOver = false"
            @drop.prevent="onDrop"
            @click="fileInputRef.click()"
          >
            <input
              ref="fileInputRef"
              type="file"
              :accept="ACCEPT_ATTR"
              class="file-input-hidden"
              @change="onFileInput"
            />

            <template v-if="!selectedFile">
              <span class="dropzone__icon"><unicon name="folder-upload" width="40" height="40" /></span>
              <p class="dropzone__text">
                Arraste o ficheiro aqui ou <span class="link">clique para selecionar</span>
              </p>
              <p class="dropzone__hint">PDF (Nubank) · CSV (Mercado Pago)</p>
            </template>

            <template v-else>
              <span class="dropzone__icon"><unicon name="file-alt" width="40" height="40" /></span>
              <p class="dropzone__filename">{{ selectedFile.name }}</p>
              <p class="dropzone__hint">{{ formatBytes(selectedFile.size) }}</p>
              <button class="btn-clear" @click.stop="clearFile" title="Remover" aria-label="Remover ficheiro">
                <unicon name="times" width="16" height="16" />
              </button>
            </template>
          </div>
        </template>

        <template v-else>
          <div class="paste-panel">
            <div class="year-row">
              <label class="year-label" for="paste-year">Ano das transações</label>
              <input
                id="paste-year"
                v-model.number="pasteYear"
                type="number"
                min="2020"
                max="2099"
                class="year-input"
              />
            </div>

            <textarea
              v-model="pasteText"
              class="paste-area"
              rows="12"
              placeholder="29 MAR •••• 1470 Mercadolivre*Mercadol - Parcela 6/12 R$ 286,58
02 ABR •••• 8812 Dl *Uber*Rides R$ 5,91
02 ABR •••• 8812 Pg *99 Ride R$ 7,06"
            />
            <p class="paste-format-hint">
              Formato: <code>DD MMM •••• NNNN Descrição R$ valor</code>
            </p>
          </div>
        </template>

        <div class="actions">
          <button
            class="btn btn--primary btn--block"
            :disabled="!canSubmit || isLoading"
            @click="submit"
          >
            <span v-if="isLoading" class="spinner" aria-hidden="true"></span>
            {{ isLoading ? 'A importar…' : 'Importar' }}
          </button>
        </div>
      </section>
    </div>
  </div>
</template>

<style scoped>
.page {
  width: 100%;
  max-width: 1200px;
}

/* ── Two-column layout ── */
.import-layout {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 24px;
  align-items: stretch;
  min-height: 480px;
}

@media (max-width: 900px) {
  .import-layout {
    grid-template-columns: 1fr;
    min-height: auto;
  }

  .panel--result {
    order: 2;
  }

  .panel--import {
    order: 1;
  }
}

.panel {
  background: var(--color-bg-secondary);
  border: 1px solid var(--color-border-dark);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm);
  padding: 24px 28px;
  min-height: 420px;
  display: flex;
  flex-direction: column;
}

/* ── Instructions (left, empty state) ── */
.instructions {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.instructions__title {
  font-size: 1.1rem;
  font-weight: 700;
  color: var(--color-text);
  margin: 0;
}

.instructions__list {
  margin: 0;
  padding-left: 1.25rem;
  display: flex;
  flex-direction: column;
  gap: 14px;
  font-size: .9rem;
  color: var(--color-text-muted);
  line-height: 1.55;
}

.instructions__list strong {
  color: var(--color-text);
}

.instructions__footer {
  margin-top: auto;
  padding-top: 16px;
  border-top: 1px solid var(--color-border-subtle-dark);
  font-size: .88rem;
  color: var(--color-text-muted);
  line-height: 1.5;
}

.result-link {
  display: inline-block;
  margin-top: 12px;
  font-size: .9rem;
  color: var(--color-accent);
  font-weight: 600;
  text-decoration: none;
}

.result-link:hover {
  color: var(--color-accent-hover);
  text-decoration: underline;
}

.panel-placeholder {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  color: var(--color-text-muted);
  font-size: .95rem;
}

/* ── Dropzone ── */
.dropzone {
  position: relative;
  flex: 1;
  border: 2px dashed var(--color-border-light);
  border-radius: var(--radius-lg);
  background: var(--color-bg-primary);
  padding: 40px 24px;
  text-align: center;
  cursor: pointer;
  transition: border-color .2s, background .2s;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-height: 280px;
}

.dropzone:hover,
.dropzone--over  { border-color: var(--color-accent); background: rgba(242, 76, 0, 0.1); }
.dropzone--ready { border-style: solid; border-color: var(--color-accent); background: rgba(242, 76, 0, 0.1); }

.file-input-hidden {
  position: absolute;
  inset: 0;
  opacity: 0;
  width: 100%;
  height: 100%;
  pointer-events: none;
}

.dropzone__icon     { display: flex; line-height: 1; }
.dropzone__text     { font-size: .95rem; color: var(--color-text); }
.dropzone__filename { font-size: 1rem; font-weight: 600; color: var(--color-text); word-break: break-all; }
.dropzone__hint     { font-size: .8rem; color: var(--color-text-muted); }
.link               { color: var(--color-accent); text-decoration: underline; }

.btn-clear {
  position: absolute;
  top: 12px;
  right: 14px;
  background: var(--color-error-bg);
  color: var(--color-error-text);
  border: none;
  border-radius: 50%;
  width: 26px;
  height: 26px;
  font-size: .8rem;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
}

.btn-clear:hover { filter: brightness(1.15); }

/* ── Paste ── */
.paste-panel {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.year-row   { display: flex; align-items: center; gap: 10px; }
.year-label { font-size: .85rem; font-weight: 500; color: var(--color-text-muted); }

.year-input {
  width: 100px;
  border: 1px solid var(--color-border-light);
  border-radius: var(--radius-sm);
  padding: 6px 10px;
  font-size: .9rem;
  outline: none;
  background: var(--color-bg-input);
  color: var(--color-text);
}

.year-input:focus { border-color: var(--color-accent); }

.paste-area {
  flex: 1;
  width: 100%;
  min-height: 240px;
  border: 1px solid var(--color-border-light);
  border-radius: var(--radius-md);
  padding: 12px 14px;
  font-family: monospace;
  font-size: .85rem;
  color: var(--color-text);
  background: var(--color-bg-input);
  resize: vertical;
  outline: none;
  line-height: 1.6;
  box-sizing: border-box;
}

.paste-area:focus { border-color: var(--color-accent); }

.paste-format-hint {
  font-size: .8rem;
  color: var(--color-text-muted);
  margin: 0;
}

.paste-format-hint code {
  font-family: monospace;
  font-size: .78rem;
  color: var(--color-accent);
  background: var(--color-bg-elevated);
  padding: 2px 6px;
  border-radius: 4px;
}

/* ── Actions ── */
.actions {
  margin-top: 20px;
}

.btn--block { width: 100%; }
.btn--sm { padding: 7px 14px; font-size: .82rem; }
.mt { margin-top: 16px; }

/* ── Result (left panel) ── */
.result-card {
  flex: 1;
  display: flex;
  flex-direction: column;
}

.result-card--success {
  background: transparent;
  border: none;
  padding: 0;
}

.result-card--error {
  background: var(--color-error-bg);
  border: 1px solid var(--color-error);
  border-radius: var(--radius-md);
  padding: 20px;
}

.result-card__header {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 20px;
}

.result-card__icon { display: flex; align-items: center; }

.result-card__header h3 {
  font-size: 1.15rem;
  font-weight: 700;
  color: var(--color-text);
  margin: 0;
}

.result-stats {
  display: flex;
  gap: 40px;
  margin-bottom: 20px;
}

.stat {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
}

.stat__value {
  font-size: 2.5rem;
  font-weight: 700;
  color: var(--color-success-text);
  line-height: 1;
}

.stat__value--muted { color: var(--color-text-muted); }

.stat__label {
  font-size: .8rem;
  color: var(--color-text-muted);
  margin-top: 6px;
}

.months-list {
  border-top: 1px solid var(--color-border-subtle-dark);
  padding-top: 16px;
  flex: 1;
}

.months-list__title {
  font-size: .85rem;
  font-weight: 600;
  color: var(--color-text);
  margin-bottom: 10px;
}

.months-list ul {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.months-list li {
  font-size: .9rem;
  color: var(--color-text-muted);
}

.result-next-hint {
  margin-top: 16px;
  font-size: .85rem;
  color: var(--color-success-text);
  font-weight: 500;
}

.error-message {
  font-size: .9rem;
  color: var(--color-error-text);
  font-family: monospace;
  word-break: break-word;
}

.spinner--muted {
  width: 28px;
  height: 28px;
  border: 2px solid var(--color-border-light);
  border-top-color: var(--color-accent);
  border-radius: 50%;
  animation: spin .7s linear infinite;
  display: inline-block;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}
</style>
