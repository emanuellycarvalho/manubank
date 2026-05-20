<script setup>
import { ref, computed } from 'vue'
import { importApi } from '@/services/api.js'
import { useProfile } from '@/composables/useProfile.js'
import { fmtMonthYear } from '@/utils/dates.js'

const { profileName } = useProfile()

const mode = ref('file')

const selectedFiles = ref([])
const dragOver      = ref(false)
const fileInputRef  = ref(null)

const pasteText = ref('')
const pasteYear = ref(new Date().getFullYear())

const isLoading = ref(false)
const result    = ref(null)

const ACCEPT_ATTR = 'text/csv,.csv'

function addFiles(fileList) {
  if (!fileList?.length) return
  result.value = null
  const existing = new Set(selectedFiles.value.map((f) => `${f.name}:${f.size}`))
  const added = []
  for (const file of fileList) {
    const key = `${file.name}:${file.size}`
    if (!existing.has(key)) {
      existing.add(key)
      added.push(file)
    }
  }
  if (added.length) selectedFiles.value = [...selectedFiles.value, ...added]
}

function onFileInput(e) {
  addFiles(e.target.files)
  if (fileInputRef.value) fileInputRef.value.value = ''
}

function onDrop(e) {
  dragOver.value = false
  addFiles(e.dataTransfer?.files)
}

function removeFile(index) {
  selectedFiles.value = selectedFiles.value.filter((_, i) => i !== index)
  if (!selectedFiles.value.length) result.value = null
}

function clearFiles() {
  selectedFiles.value = []
  result.value        = null
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
  mode.value === 'file' ? selectedFiles.value.length > 0 : pasteText.value.trim().length > 0
)

function mergeMonthGroups(target, source) {
  for (const [month, count] of Object.entries(source ?? {})) {
    target[month] = (target[month] ?? 0) + count
  }
}

function aggregateFileResults(fileResults) {
  const monthYearGroups = {}
  let totalImported = 0
  let totalSkipped = 0
  let successCount = 0
  let failCount = 0

  for (const fr of fileResults) {
    if (fr.success) {
      successCount++
      totalImported += fr.imported ?? 0
      totalSkipped += fr.skipped ?? 0
      mergeMonthGroups(monthYearGroups, fr.month_year_groups)
    } else {
      failCount++
    }
  }

  const allOk = failCount === 0 && successCount > 0
  const allFail = successCount === 0 && failCount > 0

  return {
    success: allOk || (!allFail && successCount > 0),
    partial: successCount > 0 && failCount > 0,
    allOk,
    allFail,
    imported: totalImported,
    skipped: totalSkipped,
    month_year_groups: monthYearGroups,
    files: fileResults,
    fileCount: fileResults.length,
    successCount,
    failCount,
  }
}

async function submit() {
  if (!canSubmit.value || isLoading.value) return
  isLoading.value = true
  result.value    = null

  try {
    if (mode.value === 'file') {
      const fileResults = []
      for (const file of selectedFiles.value) {
        try {
          const { data } = await importApi.upload(file, profileName.value)
          fileResults.push({ name: file.name, ...data })
        } catch (err) {
          fileResults.push({ name: file.name, success: false, error: err.message })
        }
      }
      const aggregated = aggregateFileResults(fileResults)
      result.value = aggregated
      if (aggregated.success) prepareForNextImport()
    } else {
      const { data } = await importApi.importText(
        pasteText.value.trim(),
        pasteYear.value,
        profileName.value,
      )
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
  selectedFiles.value = []
  pasteText.value     = ''
  if (fileInputRef.value) fileInputRef.value.value = ''
}

function reset() {
  clearFiles()
  pasteText.value = ''
  result.value    = null
}
</script>

<template>
  <div class="page">
    <header class="page-header">
      <div>
        <h2 class="page-title">Importar Extrato</h2>
        <p class="page-subtitle">
          Cole o texto da fatura Nubank ou envie um CSV do Mercado Pago.
          <span v-if="profileName" class="page-subtitle__hint">
            Pix com «{{ profileName }}» na descrição serão marcados como movimentação interna.
          </span>
          <span v-else class="page-subtitle__hint">
            Defina o seu nome no menu lateral para ignorar Pix entre contas suas.
          </span>
        </p>
      </div>
    </header>

    <div class="import-layout">
      <!-- Esquerda: resultado ou instruções -->
      <aside class="panel panel--result">
        <div v-if="isLoading" class="panel-placeholder panel-placeholder--loading">
          <span class="spinner spinner--muted" aria-hidden="true"></span>
          <p>
            {{
              mode === 'file' && selectedFiles.length > 1
                ? `A importar ${selectedFiles.length} ficheiros…`
                : 'A importar transações…'
            }}
          </p>
        </div>

        <div v-else-if="result?.success" class="result-card result-card--success">
          <div class="result-card__header">
            <span class="result-card__icon">
              <unicon
                :name="result.partial ? 'exclamation-triangle' : 'check-circle'"
                width="22"
                height="22"
              />
            </span>
            <h3>
              <template v-if="result.partial">Importação parcial</template>
              <template v-else-if="result.files?.length > 1">
                {{ result.files.length }} ficheiros importados
              </template>
              <template v-else>Importação concluída</template>
            </h3>
          </div>

          <p v-if="result.files?.length > 1" class="result-summary">
            {{ result.successCount }} de {{ result.fileCount }} ficheiro{{ result.fileCount === 1 ? '' : 's' }}
            processado{{ result.fileCount === 1 ? '' : 's' }} com sucesso.
          </p>

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

          <div v-if="result.files?.length > 1" class="files-report">
            <p class="files-report__title">Por ficheiro:</p>
            <ul class="files-report__list">
              <li
                v-for="fr in result.files"
                :key="fr.name"
                class="files-report__item"
                :class="fr.success ? 'files-report__item--ok' : 'files-report__item--err'"
              >
                <span class="files-report__name" :title="fr.name">{{ fr.name }}</span>
                <span v-if="fr.success" class="files-report__stats">
                  {{ fr.imported }} importadas, {{ fr.skipped }} ignoradas
                </span>
                <span v-else class="files-report__error">{{ fr.error }}</span>
              </li>
            </ul>
          </div>

          <div v-if="Object.keys(result.month_year_groups ?? {}).length" class="months-list">
            <p class="months-list__title">Meses processados (total):</p>
            <ul>
              <li v-for="(count, month) in result.month_year_groups" :key="month">
                <strong>{{ fmtMonthYear(month) }}</strong> — {{ count }} transaç{{ count === 1 ? 'ão' : 'ões' }}
              </li>
            </ul>
          </div>

          <RouterLink to="/extrato" class="result-link">Ver no extrato →</RouterLink>
        </div>

        <div v-else-if="result && !result.success" class="result-card result-card--error">
          <div class="result-card__header">
            <span class="result-card__icon"><unicon name="times-circle" width="22" height="22" /></span>
            <h3>
              <template v-if="result.files?.length > 1">Falha em todos os ficheiros</template>
              <template v-else>Falha na importação</template>
            </h3>
          </div>

          <template v-if="result.files?.length > 1">
            <ul class="files-report files-report--error">
              <li
                v-for="fr in result.files"
                :key="fr.name"
                class="files-report__item files-report__item--err"
              >
                <span class="files-report__name" :title="fr.name">{{ fr.name }}</span>
                <span class="files-report__error">{{ fr.error }}</span>
              </li>
            </ul>
          </template>
          <p v-else class="error-message">{{ result.error }}</p>

          <button class="btn btn--outline btn--sm mt" @click="reset">
            Limpar e tentar novamente
          </button>
        </div>

        <div v-else class="instructions">
          <h3 class="instructions__title">Como importar</h3>
          <ol class="instructions__list">
            <li>
              <strong>Colar texto</strong> — use o separador «Colar texto» para a fatura do cartão
              Nubank (linhas copiadas do site ou app: data, cartão e valor).
            </li>
            <li>
              <strong>Enviar ficheiro</strong> — arraste ou selecione um ou vários CSV exportados do Mercado Pago.
            </li>
            <li>
              Clique em <strong>Importar</strong>. As transações são categorizadas pelas regras
              do sistema; descrições com o seu nome (menu lateral) viram
              <strong>Movimentação interna</strong> e não entram em receitas nem despesas.
            </li>
            <li>
              Pode enviar vários ficheiros de uma vez; o relatório mostra o resultado de cada um e o total.
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
            :class="{ 'dropzone--over': dragOver, 'dropzone--ready': selectedFiles.length > 0 }"
            @dragover.prevent="dragOver = true"
            @dragleave.prevent="dragOver = false"
            @drop.prevent="onDrop"
            @click="fileInputRef.click()"
          >
            <input
              ref="fileInputRef"
              type="file"
              multiple
              :accept="ACCEPT_ATTR"
              class="file-input-hidden"
              @change="onFileInput"
            />

            <template v-if="!selectedFiles.length">
              <span class="dropzone__icon"><unicon name="folder-upload" width="40" height="40" /></span>
              <p class="dropzone__text">
                Arraste os ficheiros aqui ou <span class="link">clique para selecionar</span>
              </p>
              <p class="dropzone__hint">Um ou vários CSV (Mercado Pago)</p>
            </template>

            <template v-else>
              <div class="dropzone__files" @click.stop>
                <p class="dropzone__files-title">
                  {{ selectedFiles.length }} ficheiro{{ selectedFiles.length === 1 ? '' : 's' }} selecionado{{ selectedFiles.length === 1 ? '' : 's' }}
                </p>
                <ul class="file-list">
                  <li v-for="(file, idx) in selectedFiles" :key="`${file.name}-${file.size}`" class="file-list__item">
                    <span class="file-list__icon"><unicon name="file-alt" width="18" height="18" /></span>
                    <span class="file-list__info">
                      <span class="file-list__name">{{ file.name }}</span>
                      <span class="file-list__size">{{ formatBytes(file.size) }}</span>
                    </span>
                    <button
                      class="btn-clear btn-clear--inline"
                      title="Remover"
                      aria-label="Remover ficheiro"
                      @click.stop="removeFile(idx)"
                    >
                      <unicon name="times" width="14" height="14" />
                    </button>
                  </li>
                </ul>
                <button class="btn-add-more" @click.stop="fileInputRef.click()">
                  + Adicionar mais ficheiros
                </button>
                <button class="btn-clear-all" @click.stop="clearFiles">
                  Limpar todos
                </button>
              </div>
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
            {{
              isLoading
                ? (selectedFiles.length > 1 ? `A importar ${selectedFiles.length} ficheiros…` : 'A importar…')
                : (mode === 'file' && selectedFiles.length > 1
                    ? `Importar ${selectedFiles.length} ficheiros`
                    : 'Importar')
            }}
          </button>
        </div>
      </section>
    </div>
  </div>
</template>

<style scoped>
.page {
  width: 100%;
  max-width: none;
  display: flex;
  flex-direction: column;
  flex: 1;
  min-height: 0;
}

.page-header {
  flex-shrink: 0;
  margin-bottom: 20px;
}

/* ── Two-column layout ── */
.import-layout {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 24px;
  align-items: stretch;
  flex: 1;
  min-height: 0;
}

@media (max-width: 900px) {
  .import-layout {
    grid-template-columns: 1fr;
    flex: 1;
    min-height: 0;
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
  min-height: 0;
  height: 100%;
  display: flex;
  flex-direction: column;
}

.panel--import .tabs,
.actions {
  flex-shrink: 0;
}

/* ── Instructions (left, empty state) ── */
.instructions {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
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
  min-height: 0;
}

.dropzone:hover,
.dropzone--over  { border-color: var(--color-accent); background: rgba(242, 76, 0, 0.1); }
.dropzone--ready { border-style: solid; border-color: var(--color-accent); background: rgba(242, 76, 0, 0.1); align-items: stretch; text-align: left; }

.dropzone__files {
  width: 100%;
  height: 100%;
  display: flex;
  flex-direction: column;
  gap: 10px;
  min-height: 0;
}

.dropzone__files-title {
  font-size: .9rem;
  font-weight: 600;
  color: var(--color-text);
  margin: 0;
  text-align: center;
}

.file-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
  flex: 1;
  min-height: 0;
  overflow-y: auto;
}

.file-list__item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 10px;
  background: var(--color-bg-secondary);
  border: 1px solid var(--color-border-subtle-dark);
  border-radius: var(--radius-sm);
}

.file-list__icon { display: flex; flex-shrink: 0; color: var(--color-accent); }
.file-list__info { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
.file-list__name { font-size: .88rem; font-weight: 600; color: var(--color-text); word-break: break-all; }
.file-list__size { font-size: .75rem; color: var(--color-text-muted); }

.btn-clear--inline {
  position: static;
  flex-shrink: 0;
}

.btn-add-more,
.btn-clear-all {
  font-size: .82rem;
  padding: 6px 12px;
  border-radius: var(--radius-sm);
  cursor: pointer;
  border: 1px solid var(--color-border-light);
  background: var(--color-bg-secondary);
  color: var(--color-text-muted);
}

.btn-add-more {
  color: var(--color-accent);
  border-color: var(--color-accent);
  align-self: center;
}

.btn-add-more:hover { background: rgba(242, 76, 0, 0.1); }
.btn-clear-all:hover { color: var(--color-error-text); border-color: var(--color-error); }

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
  min-height: 0;
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
  min-height: 0;
  overflow-y: auto;
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

.result-summary {
  font-size: .9rem;
  color: var(--color-text-muted);
  margin: -8px 0 16px;
}

.files-report {
  border-top: 1px solid var(--color-border-subtle-dark);
  padding-top: 14px;
  margin-bottom: 16px;
}

.files-report__title {
  font-size: .85rem;
  font-weight: 600;
  color: var(--color-text);
  margin: 0 0 10px;
}

.files-report__list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
  max-height: 200px;
  overflow-y: auto;
}

.files-report__item {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 8px 10px;
  border-radius: var(--radius-sm);
  font-size: .85rem;
}

.files-report__item--ok {
  background: var(--color-bg-primary);
  border: 1px solid var(--color-border-subtle-dark);
}

.files-report__item--err {
  background: var(--color-error-bg);
  border: 1px solid var(--color-error);
}

.files-report__name {
  font-weight: 600;
  color: var(--color-text);
  word-break: break-all;
}

.files-report__stats {
  color: var(--color-text-muted);
}

.files-report__error {
  color: var(--color-error-text);
  font-family: monospace;
  font-size: .8rem;
  word-break: break-word;
}

.files-report--error {
  border-top: none;
  padding-top: 0;
  margin-bottom: 12px;
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

/* Abas do painel de importação: ícone acompanha o texto (accent no hover/ativo) */
.page-subtitle__hint {
  display: block;
  margin-top: 6px;
  font-size: 0.85rem;
  color: var(--color-text-muted);
}

.panel--import .tab:hover {
  color: var(--color-accent);
}

.panel--import .tab:hover :deep(svg),
.panel--import .tab--active :deep(svg) {
  fill: var(--color-accent);
}
</style>
