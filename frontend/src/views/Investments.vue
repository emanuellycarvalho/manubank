<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue'
import { investmentsApi } from '@/services/api.js'
import ConfirmModal from '@/components/ConfirmModal.vue'
import YieldGrowthChart from '@/components/charts/YieldGrowthChart.vue'
import { useTableSort } from '@/composables/useTableSort.js'
import { toIsoDate, defaultDashboardDateRange } from '@/utils/dates.js'
import {
  getFinancePeriodStartDay,
  getCycleKeyForDate,
  listCycleKeysBetween,
  formatCycleLabel,
} from '@/utils/periodCycle.js'
import {
  formatBrlCurrencyInput,
  maskBrlCurrencyInput,
  parseBrlToNumber,
} from '@/utils/currency.js'

const ENTRY_SORT_COLS = [
  { key: 'date', getValue: (e) => e.date },
  { key: 'description', getValue: (e) => e.description ?? '' },
  { key: 'amount', getValue: (e) => e.amount },
]

const FOREX_URL = 'https://economia.awesomeapi.com.br/last/USD-BRL,EUR-BRL,GBP-BRL'

const objectives = ref([])
const activeId = ref(null)
const isLoading = ref(false)
const isSubmitting = ref(false)
const isDeleting = ref(false)
const errorMsg = ref('')
const successMsg = ref('')

const forexRates = ref(null)
const forexLoading = ref(false)
const forexError = ref('')

const entryToDelete = ref(null)
const objectiveToDelete = ref(null)
const entryToEdit = ref(null)

const showObjectiveModal = ref(false)
/** null = criar; number = id do objetivo em edição */
const editingObjectiveId = ref(null)
const isSavingObjective = ref(false)

const EMPTY_OBJECTIVE = () => ({
  name: '',
  target_amount: '',
  end_date: '',
})
const objectiveModalForm = reactive(EMPTY_OBJECTIVE())

const objectiveModalTitle = computed(() =>
  editingObjectiveId.value ? 'Editar objetivo' : 'Novo objetivo',
)

const EMPTY_ENTRY = () => ({
  date: toIsoDate(new Date()),
  type: 'entrada',
  amount: '',
  description: '',
})
const entryForm = reactive(EMPTY_ENTRY())
const entryEditForm = reactive(EMPTY_ENTRY())

const brl = (v) =>
  new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(v) || 0)

const foreign = (v) =>
  new Intl.NumberFormat('pt-BR', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(Number(v) || 0)

const pct = (v) =>
  new Intl.NumberFormat('pt-BR', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
  }).format(Number(v) || 0)

function fmtDate(d) {
  if (!d) return '—'
  const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(d))
  if (!m) return d
  return `${m[3]}/${m[2]}/${m[1]}`
}

const activeObjective = computed(() =>
  objectives.value.find((o) => o.id === activeId.value) ?? null,
)

const historico = computed(() => activeObjective.value?.historico ?? [])

const yieldDateRange = computed(() => {
  const hist = historico.value
  if (!hist.length) return defaultDashboardDateRange()

  const dates = hist.map((e) => e.date).filter(Boolean).sort()
  const start = dates[0]
  const end = toIsoDate(new Date())
  if (!start || start > end) return defaultDashboardDateRange()

  return { start, end }
})

/** Lançamentos de rendimento no objetivo (entradas cuja descrição contém "rendimento"). */
function isYieldEntry(entry) {
  const desc = (entry.description ?? '').toLowerCase()
  return entry.type === 'entrada' && desc.includes('rendimento')
}

const yieldChartData = computed(() => {
  const entries = historico.value.filter(isYieldEntry)
  if (!entries.length) {
    return { labels: [], monthly: [], accumulated: [] }
  }

  const periodDay = getFinancePeriodStartDay()
  const dates = entries.map((e) => e.date).filter(Boolean).sort()
  const rangeEnd = yieldDateRange.value.end
  const cycleKeys = listCycleKeysBetween(dates[0], rangeEnd, periodDay)

  if (!cycleKeys.length) {
    return { labels: [], monthly: [], accumulated: [] }
  }

  const byCycle = {}
  for (const entry of entries) {
    const key = getCycleKeyForDate(entry.date, periodDay)
    byCycle[key] = (byCycle[key] ?? 0) + Number(entry.amount)
  }

  let running = 0
  const monthly = []
  const accumulated = []

  for (const key of cycleKeys) {
    const value = byCycle[key] ?? 0
    running += value
    monthly.push(Math.round(value * 100) / 100)
    accumulated.push(Math.round(running * 100) / 100)
  }

  return {
    labels: cycleKeys.map((key) => formatCycleLabel(key, periodDay)),
    monthly,
    accumulated,
  }
})

const yieldLabels = computed(() => yieldChartData.value.labels)
const yieldMonthly = computed(() => yieldChartData.value.monthly)
const yieldAccumulated = computed(() => yieldChartData.value.accumulated)

const { sortedItems: sortedHistorico, toggleSort, sortClass } = useTableSort(
  historico,
  ENTRY_SORT_COLS,
  { key: 'date', dir: 'desc' },
)

const forexConversions = computed(() => {
  const accumulated = activeObjective.value?.valor_acumulado ?? 0
  const rates = forexRates.value

  if (!rates || accumulated === 0) {
    return [
      { code: 'USD', label: 'Dólar (USD)', value: null },
      { code: 'EUR', label: 'Euro (EUR)', value: null },
      { code: 'GBP', label: 'Libra (GBP)', value: null },
    ]
  }

  return [
    { code: 'USD', label: 'Dólar (USD)', value: accumulated / rates.USD },
    { code: 'EUR', label: 'Euro (EUR)', value: accumulated / rates.EUR },
    { code: 'GBP', label: 'Libra (GBP)', value: accumulated / rates.GBP },
  ]
})

const metricRows = computed(() => {
  const o = activeObjective.value
  if (!o) return { row1: [], row2: [] }

  const mesesLabel = (n) => `${n} ${n === 1 ? 'mês' : 'meses'}`

  return {
    row1: [
      { key: 'acumulado', label: 'Valor acumulado', value: brl(o.valor_acumulado), highlight: true },
      { key: 'meta', label: 'Meta', value: brl(o.target_amount) },
      { key: 'restante', label: 'Tempo restante', value: mesesLabel(o.tempo_restante_meses) },
    ],
    row2: [
      { key: 'medio', label: 'Investimento mensal médio', value: brl(o.investimento_mensal_medio) },
      { key: 'pct', label: 'Progresso', value: `${pct(o.porcentagem_alcancada)}%` },
      { key: 'end_date', label: 'Data da meta', value: fmtDate(o.end_date) },
    ],
  }
})

async function loadObjectives() {
  isLoading.value = true
  errorMsg.value = ''

  try {
    const { data: body } = await investmentsApi.list()

    if (body?.success === false) {
      throw new Error(body.error ?? 'Erro ao carregar investimentos.')
    }

    const list = body?.data ?? body ?? []
    objectives.value = Array.isArray(list) ? list : []

    if (objectives.value.length === 0) {
      activeId.value = null
      return
    }

    const stillExists = objectives.value.some((o) => o.id === activeId.value)
    if (!stillExists) {
      activeId.value = objectives.value[0].id
    }
  } catch (err) {
    errorMsg.value = err.message
    objectives.value = []
    activeId.value = null
  } finally {
    isLoading.value = false
  }
}

async function loadForex() {
  forexLoading.value = true
  forexError.value = ''

  try {
    const res = await fetch(FOREX_URL)
    if (!res.ok) throw new Error('Falha ao obter cotações.')

    const json = await res.json()
    const usd = parseFloat(json?.USDBRL?.ask)
    const eur = parseFloat(json?.EURBRL?.ask)
    const gbp = parseFloat(json?.GBPBRL?.ask)

    if (!usd || !eur || !gbp) {
      throw new Error('Cotações incompletas na resposta da API.')
    }

    forexRates.value = { USD: usd, EUR: eur, GBP: gbp }
  } catch (err) {
    forexRates.value = null
    forexError.value = err.message
  } finally {
    forexLoading.value = false
  }
}

function parsePositiveAmount(raw) {
  const n = parseFloat(String(raw).replace(',', '.'))
  if (Number.isNaN(n) || n <= 0) return null
  return n
}

function openCreateObjectiveModal() {
  editingObjectiveId.value = null
  Object.assign(objectiveModalForm, EMPTY_OBJECTIVE())
  showObjectiveModal.value = true
  errorMsg.value = ''
}

function openEditObjectiveModal() {
  const o = activeObjective.value
  if (!o) return
  editingObjectiveId.value = o.id
  Object.assign(objectiveModalForm, {
    name: o.name,
    target_amount: formatBrlCurrencyInput(o.target_amount),
    end_date: o.end_date,
  })
  showObjectiveModal.value = true
  errorMsg.value = ''
}

function closeObjectiveModal() {
  showObjectiveModal.value = false
  editingObjectiveId.value = null
}

function openEditEntryModal(entry) {
  entryToEdit.value = entry
  Object.assign(entryEditForm, {
    date: entry.date,
    type: entry.type,
    amount: String(entry.amount),
    description: entry.description ?? '',
  })
}

function closeEditEntryModal() {
  entryToEdit.value = null
}

function onObjectiveTargetInput(event) {
  objectiveModalForm.target_amount = maskBrlCurrencyInput(event.target.value)
}

async function submitObjectiveModal() {
  const target = parseBrlToNumber(objectiveModalForm.target_amount)
  if (!objectiveModalForm.name.trim()) {
    errorMsg.value = 'Informe o nome do objetivo.'
    return
  }
  if (!target || target <= 0) {
    errorMsg.value = 'A meta deve ser um valor positivo.'
    return
  }
  if (!objectiveModalForm.end_date) {
    errorMsg.value = 'Informe a data limite.'
    return
  }

  isSavingObjective.value = true
  errorMsg.value = ''

  try {
    if (editingObjectiveId.value) {
      await investmentsApi.updateObjective({
        objective_id: editingObjectiveId.value,
        name: objectiveModalForm.name.trim(),
        target_amount: target,
        end_date: objectiveModalForm.end_date,
      })
      showSuccess('Objetivo atualizado.')
    } else {
      const { data: body } = await investmentsApi.createObjective({
        name: objectiveModalForm.name.trim(),
        target_amount: target,
        end_date: objectiveModalForm.end_date,
      })
      const newId = body?.objective_id
      if (newId) activeId.value = newId
      showSuccess('Objetivo criado.')
    }
    closeObjectiveModal()
    await loadObjectives()
  } catch (err) {
    errorMsg.value = err.message
  } finally {
    isSavingObjective.value = false
  }
}

function requestDeleteObjective() {
  const o = activeObjective.value
  if (!o) return
  objectiveToDelete.value = o
  closeObjectiveModal()
}

async function confirmDeleteObjective() {
  const obj = objectiveToDelete.value
  if (!obj) return

  isSavingObjective.value = true
  errorMsg.value = ''

  try {
    await investmentsApi.removeObjective(obj.id)
    objectiveToDelete.value = null
    showSuccess(`"${obj.name}" removido.`)
    await loadObjectives()
  } catch (err) {
    errorMsg.value = err.message
  } finally {
    isSavingObjective.value = false
  }
}

async function submitEntry() {
  const objective = activeObjective.value
  if (!objective) return

  const amount = parsePositiveAmount(entryForm.amount)
  if (!entryForm.date) {
    errorMsg.value = 'Informe a data do lançamento.'
    return
  }
  if (!amount) {
    errorMsg.value = 'Informe um valor positivo.'
    return
  }

  isSubmitting.value = true
  errorMsg.value = ''

  try {
    await investmentsApi.addEntry({
      objective_id: objective.id,
      type: entryForm.type,
      amount,
      date: entryForm.date,
      description: entryForm.description.trim(),
    })

    Object.assign(entryForm, EMPTY_ENTRY())
    showSuccess('Lançamento registado com sucesso.')
    await loadObjectives()
  } catch (err) {
    errorMsg.value = err.message
  } finally {
    isSubmitting.value = false
  }
}

async function submitEditEntry() {
  const entry = entryToEdit.value
  if (!entry) return

  const amount = parsePositiveAmount(entryEditForm.amount)
  if (!entryEditForm.date) {
    errorMsg.value = 'Informe a data do lançamento.'
    return
  }
  if (!amount) {
    errorMsg.value = 'Informe um valor positivo.'
    return
  }

  isSubmitting.value = true
  errorMsg.value = ''

  try {
    await investmentsApi.updateEntry({
      entry_id: entry.id,
      type: entryEditForm.type,
      amount,
      date: entryEditForm.date,
      description: entryEditForm.description.trim(),
    })
    closeEditEntryModal()
    showSuccess('Lançamento atualizado.')
    await loadObjectives()
  } catch (err) {
    errorMsg.value = err.message
  } finally {
    isSubmitting.value = false
  }
}

function requestDeleteEntry(entry) {
  entryToDelete.value = entry
}

async function confirmDeleteEntry() {
  const entry = entryToDelete.value
  if (!entry) return

  isDeleting.value = true
  errorMsg.value = ''

  try {
    await investmentsApi.removeEntry(entry.id)
    entryToDelete.value = null
    showSuccess('Lançamento removido.')
    await loadObjectives()
  } catch (err) {
    errorMsg.value = err.message
  } finally {
    isDeleting.value = false
  }
}

function selectTab(id) {
  activeId.value = id
}

function onObjectiveModalOverlay(e) {
  if (e.target === e.currentTarget && !isSavingObjective.value) {
    closeObjectiveModal()
  }
}

function amountClass(type) {
  return type === 'saída' ? 'amount--out' : 'amount--in'
}

let successTimer = null
function showSuccess(msg) {
  successMsg.value = msg
  clearTimeout(successTimer)
  successTimer = setTimeout(() => { successMsg.value = '' }, 3500)
}

watch(
  () => activeObjective.value?.valor_acumulado,
  () => {
    if (activeObjective.value && !forexRates.value && !forexLoading.value) {
      loadForex()
    }
  },
)

onMounted(async () => {
  await Promise.all([loadObjectives(), loadForex()])
})
</script>

<template>
  <div class="page investments-page">
    <header class="page-header investments-header">
      <div>
        <h2 class="page-title">Investimentos</h2>
        <p class="page-subtitle">
          Metas de longo prazo, aportes e conversão cambial do valor acumulado
        </p>
      </div>
      <div class="investments-header__actions">
        <button type="button" class="btn btn--primary btn--sm" @click="openCreateObjectiveModal">
          <unicon name="plus" width="16" height="16" />
          Novo objetivo
        </button>
        <button
          type="button"
          class="btn btn--outline btn--sm"
          :disabled="forexLoading"
          title="Atualizar cotações"
          @click="loadForex"
        >
          <span v-if="forexLoading" class="spinner-ui spinner-ui--sm" aria-hidden="true"></span>
          <unicon v-else name="refresh" width="16" height="16" />
          Cotações
        </button>
      </div>
    </header>

    <div v-if="successMsg" class="alert alert--success" role="status">
      <unicon name="check-circle" width="16" height="16" />
      {{ successMsg }}
    </div>
    <div v-if="errorMsg" class="alert alert--error" role="alert">
      <unicon name="times-circle" width="16" height="16" />
      {{ errorMsg }}
    </div>

    <div v-if="isLoading" class="loading-state">
      <span class="spinner-ui spinner-ui--lg" aria-hidden="true"></span>
      Carregando objetivos…
    </div>

    <div v-else-if="objectives.length === 0" class="empty-state card">
      <unicon name="wallet" width="40" height="40" class="empty-icon" />
      <p>Nenhum objetivo cadastrado ainda.</p>
      <button type="button" class="btn btn--primary" @click="openCreateObjectiveModal">
        <unicon name="plus" width="16" height="16" />
        Criar primeiro objetivo
      </button>
    </div>

    <template v-else>
      <!-- Abas de objetivos (mesmo padrão Import.vue) -->
      <div class="objectives-toolbar">
        <div class="tabs investments-tabs" role="tablist" aria-label="Objetivos de investimento">
          <button
            v-for="obj in objectives"
            :key="obj.id"
            type="button"
            role="tab"
            class="tab"
            :class="{ 'tab--active': obj.id === activeId }"
            :aria-selected="obj.id === activeId"
            @click="selectTab(obj.id)"
          >
            {{ obj.name }}
          </button>
        </div>
        <button
          v-if="activeObjective"
          type="button"
          class="btn btn--outline btn--sm objectives-toolbar__edit"
          @click="openEditObjectiveModal"
        >
          <unicon name="edit-alt" width="16" height="16" />
          Editar objetivo
        </button>
      </div>

      <template v-if="activeObjective">
        <div class="investments-active">
        <div class="investments-top">
        <!-- Métricas (50%) -->
        <section class="metrics-panel" aria-label="Indicadores do objetivo">
          <div class="metrics-grid metrics-grid--row">
            <article
              v-for="card in metricRows.row1"
              :key="card.key"
              class="metric-card"
              :class="{ 'metric-card--highlight': card.highlight }"
            >
              <span class="metric-card__label">{{ card.label }}</span>
              <span class="metric-card__value">{{ card.value }}</span>
            </article>
          </div>
          <div class="metrics-grid metrics-grid--row">
            <article
              v-for="card in metricRows.row2"
              :key="card.key"
              class="metric-card"
            >
              <span class="metric-card__label">{{ card.label }}</span>
              <span class="metric-card__value">{{ card.value }}</span>
            </article>
          </div>
        </section>

        <!-- Câmbio -->
        <section class="forex-panel panel" aria-label="Conversão cambial">
          <div class="forex-panel__head">
            <h3 class="forex-panel__title">Equivalente em moeda estrangeira</h3>
            <p class="forex-panel__subtitle">
              Com base no valor acumulado de <strong>{{ brl(activeObjective.valor_acumulado) }}</strong>
              (cotação de venda — ask)
            </p>
          </div>

          <div v-if="forexLoading" class="forex-panel__loading">
            <span class="spinner-ui spinner-ui--md" aria-hidden="true"></span>
            Atualizando cotações…
          </div>
          <div v-else-if="forexError" class="forex-panel__error">
            <unicon name="exclamation-triangle" width="18" height="18" />
            {{ forexError }}
          </div>
          <div v-else class="forex-grid">
            <div
              v-for="item in forexConversions"
              :key="item.code"
              class="forex-item"
            >
              <span class="forex-item__label">{{ item.label }}</span>
              <span class="forex-item__value">
                <template v-if="item.value != null">
                  {{ item.code }} {{ foreign(item.value) }}
                </template>
                <template v-else>—</template>
              </span>
            </div>
          </div>
        </section>
        </div>

        <div class="investments-main">
          <!-- Histórico (50%) -->
          <section class="card history-card investments-main__history">
            <h3 class="section-title">Histórico</h3>
            <div class="table-wrap">
              <table class="table table--investments">
                <thead>
                  <tr>
                    <th
                      class="th-sortable"
                      :class="sortClass('date')"
                      @click="toggleSort('date')"
                    >
                      Data
                    </th>
                    <th
                      class="th-sortable"
                      :class="sortClass('description')"
                      @click="toggleSort('description')"
                    >
                      Descrição
                    </th>
                    <th
                      class="th-sortable col-amount"
                      :class="sortClass('amount')"
                      @click="toggleSort('amount')"
                    >
                      Valor
                    </th>
                    <th class="col-actions">Ações</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="sortedHistorico.length === 0">
                    <td colspan="4" class="table-empty">Nenhum lançamento neste objetivo.</td>
                  </tr>
                  <tr v-for="entry in sortedHistorico" :key="entry.id">
                    <td class="col-date">{{ fmtDate(entry.date) }}</td>
                    <td class="col-desc">{{ entry.description || '—' }}</td>
                    <td class="col-amount">
                      <span :class="amountClass(entry.type)">{{ brl(entry.amount) }}</span>
                    </td>
                    <td class="col-actions">
                      <div class="row-actions">
                        <button
                          type="button"
                          class="btn-icon"
                          title="Editar lançamento"
                          @click="openEditEntryModal(entry)"
                        >
                          <unicon name="edit-alt" width="16" height="16" />
                        </button>
                        <button
                          type="button"
                          class="btn-icon btn-icon--danger"
                          title="Excluir lançamento"
                          :disabled="isDeleting"
                          @click="requestDeleteEntry(entry)"
                        >
                          <unicon name="trash-alt" width="16" height="16" />
                        </button>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </section>

          <div class="investments-main__aside">
            <!-- Formulário (50%) -->
            <section class="card entry-form-card">
              <h3 class="section-title">Novo lançamento</h3>
              <form class="entry-form" @submit.prevent="submitEntry" novalidate>
                <div class="entry-form__grid">
                  <div class="form-group entry-form__field">
                    <label class="form-label" for="entry-date">Data</label>
                    <input
                      id="entry-date"
                      v-model="entryForm.date"
                      type="date"
                      class="form-control"
                      required
                    />
                  </div>
                  <div class="form-group entry-form__field">
                    <label class="form-label" for="entry-type">Tipo</label>
                    <select id="entry-type" v-model="entryForm.type" class="form-control app-select">
                      <option value="entrada">Entrada</option>
                      <option value="saída">Saída</option>
                    </select>
                  </div>
                  <div class="form-group entry-form__field entry-form__field--full">
                    <label class="form-label" for="entry-amount">Valor (R$)</label>
                    <input
                      id="entry-amount"
                      v-model="entryForm.amount"
                      type="number"
                      class="form-control"
                      min="0.01"
                      step="0.01"
                      placeholder="0,00"
                      required
                    />
                  </div>
                  <div class="form-group entry-form__field entry-form__field--full">
                    <label class="form-label" for="entry-desc">Descrição</label>
                    <input
                      id="entry-desc"
                      v-model.trim="entryForm.description"
                      type="text"
                      class="form-control"
                      placeholder="Opcional"
                      maxlength="200"
                    />
                  </div>
                </div>
                <div class="entry-form__actions">
                  <button type="submit" class="btn btn--primary btn--block" :disabled="isSubmitting">
                    <span v-if="isSubmitting" class="spinner-ui spinner-ui--sm" aria-hidden="true"></span>
                    {{ isSubmitting ? 'Salvando…' : 'Registrar lançamento' }}
                  </button>
                </div>
              </form>
            </section>

            <!-- Rendimentos (abaixo do formulário) -->
            <section class="card yield-chart-card" aria-label="Evolução de rendimentos">
              <h3 class="section-title">Rendimentos por mês</h3>
              <p class="yield-chart-card__desc">
                Ciclo com início no dia {{ getFinancePeriodStartDay() }} (configurado no dashboard).
              </p>
              <YieldGrowthChart
                :labels="yieldLabels"
                :monthly-yield="yieldMonthly"
                :accumulated-yield="yieldAccumulated"
              />
            </section>
          </div>
        </div>
        </div>
      </template>
    </template>

    <!-- Modal: criar / editar objetivo -->
    <Transition name="modal">
      <div
        v-if="showObjectiveModal"
        class="modal-overlay"
        @click="onObjectiveModalOverlay"
      >
        <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="obj-modal-title">
          <header class="modal-card__header">
            <h3 id="obj-modal-title" class="modal-card__title">{{ objectiveModalTitle }}</h3>
            <button
              type="button"
              class="btn-icon"
              aria-label="Fechar"
              :disabled="isSavingObjective"
              @click="closeObjectiveModal"
            >
              <unicon name="times" width="18" height="18" />
            </button>
          </header>
          <form class="modal-card__body" @submit.prevent="submitObjectiveModal" novalidate>
            <div class="form-group">
              <label class="form-label" for="modal-obj-name">Nome *</label>
              <input
                id="modal-obj-name"
                v-model.trim="objectiveModalForm.name"
                type="text"
                class="form-control"
                placeholder="Ex: Imóvel, Intercâmbio"
                maxlength="80"
                required
                autofocus
              />
            </div>
            <div class="form-group">
              <label class="form-label" for="modal-obj-target">Meta *</label>
              <input
                id="modal-obj-target"
                :value="objectiveModalForm.target_amount"
                type="text"
                class="form-control form-control--currency"
                inputmode="decimal"
                autocomplete="off"
                placeholder="R$ 0,00"
                required
                @input="onObjectiveTargetInput"
              />
            </div>
            <div class="form-group">
              <label class="form-label" for="modal-obj-end">Data limite *</label>
              <input
                id="modal-obj-end"
                v-model="objectiveModalForm.end_date"
                type="date"
                class="form-control"
                required
              />
            </div>
            <footer class="modal-card__footer modal-card__footer--split">
              <button
                v-if="editingObjectiveId"
                type="button"
                class="btn btn--danger btn--sm"
                :disabled="isSavingObjective"
                @click="requestDeleteObjective"
              >
                Excluir objetivo
              </button>
              <div class="modal-card__footer-end">
                <button
                  type="button"
                  class="btn btn--outline"
                  :disabled="isSavingObjective"
                  @click="closeObjectiveModal"
                >
                  Cancelar
                </button>
                <button type="submit" class="btn btn--primary" :disabled="isSavingObjective">
                  <span v-if="isSavingObjective" class="spinner-ui spinner-ui--sm" aria-hidden="true"></span>
                  {{
                    isSavingObjective
                      ? 'Salvando…'
                      : editingObjectiveId
                        ? 'Salvar alterações'
                        : 'Criar objetivo'
                  }}
                </button>
              </div>
            </footer>
          </form>
        </div>
      </div>
    </Transition>

    <!-- Modal: editar lançamento -->
    <Transition name="modal">
      <div
        v-if="entryToEdit"
        class="modal-overlay"
        @click="(e) => e.target === e.currentTarget && !isSubmitting && closeEditEntryModal()"
      >
        <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="entry-modal-title">
          <header class="modal-card__header">
            <h3 id="entry-modal-title" class="modal-card__title">Editar lançamento</h3>
            <button
              type="button"
              class="btn-icon"
              aria-label="Fechar"
              :disabled="isSubmitting"
              @click="closeEditEntryModal"
            >
              <unicon name="times" width="18" height="18" />
            </button>
          </header>
          <form class="modal-card__body" @submit.prevent="submitEditEntry" novalidate>
            <div class="form-group">
              <label class="form-label" for="edit-entry-date">Data</label>
              <input
                id="edit-entry-date"
                v-model="entryEditForm.date"
                type="date"
                class="form-control"
                required
              />
            </div>
            <div class="form-group">
              <label class="form-label" for="edit-entry-type">Tipo</label>
              <select id="edit-entry-type" v-model="entryEditForm.type" class="form-control app-select">
                <option value="entrada">Entrada</option>
                <option value="saída">Saída</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label" for="edit-entry-amount">Valor (R$)</label>
              <input
                id="edit-entry-amount"
                v-model="entryEditForm.amount"
                type="number"
                class="form-control"
                step="0.01"
                required
              />
            </div>
            <div class="form-group">
              <label class="form-label" for="edit-entry-desc">Descrição</label>
              <input
                id="edit-entry-desc"
                v-model.trim="entryEditForm.description"
                type="text"
                class="form-control"
                maxlength="200"
              />
            </div>
            <footer class="modal-card__footer">
              <button
                type="button"
                class="btn btn--outline"
                :disabled="isSubmitting"
                @click="closeEditEntryModal"
              >
                Cancelar
              </button>
              <button type="submit" class="btn btn--primary" :disabled="isSubmitting">
                <span v-if="isSubmitting" class="spinner-ui spinner-ui--sm" aria-hidden="true"></span>
                {{ isSubmitting ? 'Salvando…' : 'Salvar' }}
              </button>
            </footer>
          </form>
        </div>
      </div>
    </Transition>

    <ConfirmModal
      v-if="entryToDelete"
      title="Excluir lançamento?"
      message="Este aporte será removido permanentemente do histórico."
      :detail="entryToDelete.description ? `Descrição: ${entryToDelete.description}` : ''"
      confirm-label="Excluir"
      :loading="isDeleting"
      @confirm="confirmDeleteEntry"
      @close="entryToDelete = null"
    />

    <ConfirmModal
      v-if="objectiveToDelete"
      title="Excluir objetivo?"
      :message="'Remover ' + objectiveToDelete.name + ' e todos os lançamentos?'"
      confirm-label="Excluir"
      :loading="isSavingObjective"
      @confirm="confirmDeleteObjective"
      @close="objectiveToDelete = null"
    />
  </div>
</template>

<style scoped>
.investments-page {
  width: 100%;
  max-width: none;
}

.investments-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 24px;
}

.investments-header__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
}

.btn--danger {
  background: var(--color-error);
  color: var(--color-text);
}

.row-actions {
  display: flex;
  gap: 6px;
  justify-content: center;
}

.col-actions {
  width: 96px;
}

/* Modais */
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
  border: 1px solid var(--color-border-dark);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-md);
}

.modal-card__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 18px;
  border-bottom: 1px solid var(--color-border-dark);
}

.modal-card__title {
  margin: 0;
  font-size: 1.05rem;
  font-weight: 700;
}

.modal-card__body {
  padding: 18px;
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.modal-card__footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  margin-top: 4px;
}

.modal-card__footer--split {
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
}

.modal-card__footer-end {
  display: flex;
  gap: 10px;
  margin-left: auto;
}

/* Tabela (padrão extrato) */
.table--investments .col-date {
  white-space: nowrap;
  color: var(--color-text-muted);
  font-size: 0.84rem;
}

.table--investments .col-desc {
  max-width: none;
  white-space: normal;
}

.table--investments .col-amount {
  text-align: right;
  font-weight: 600;
  white-space: nowrap;
}

.table--investments .amount--in {
  color: var(--color-success-text);
}

.table--investments .amount--out {
  color: var(--color-error-text);
}

.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.2s ease;
}

.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}

.form-control--currency {
  font-variant-numeric: tabular-nums;
  letter-spacing: 0.02em;
}

.alert :deep(svg) {
  fill: currentColor;
  flex-shrink: 0;
}

.loading-state,
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  padding: 48px 24px;
  color: var(--color-text-muted);
  font-size: 0.95rem;
}

.empty-hint {
  font-size: 0.85rem;
  opacity: 0.85;
  text-align: center;
  max-width: 360px;
}

.empty-icon :deep(svg) {
  fill: var(--color-text-subtle);
}

/* Abas de objetivos (padrão global .tabs + ícones como Import) */
.objectives-toolbar {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px 16px;
  margin-bottom: 0;
}

.objectives-toolbar__edit {
  flex-shrink: 0;
}

.investments-tabs {
  flex: 1;
  min-width: 0;
  flex-wrap: wrap;
  overflow: visible;
  margin-bottom: 20px;
}

.investments-tabs .tab {
  white-space: nowrap;
}

.investments-tabs .tab:hover {
  color: var(--color-accent);
}

.investments-tabs .tab:hover :deep(svg),
.investments-tabs .tab--active :deep(svg) {
  fill: var(--color-accent);
}

.investments-active {
  display: flex;
  flex-direction: column;
  gap: 20px;
  width: 100%;
}

.investments-top {
  display: grid;
  grid-template-columns: 1fr;
  gap: 20px;
  width: 100%;
}

@media (min-width: 768px) {
  .investments-top {
    grid-template-columns: 1fr 1fr;
    align-items: stretch;
  }
}

.investments-main {
  display: grid;
  grid-template-columns: 1fr;
  gap: 20px;
  width: 100%;
  align-items: start;
}

@media (min-width: 1024px) {
  .investments-main {
    grid-template-columns: 1fr 1fr;
  }
}

.investments-main__history,
.investments-main__aside {
  min-width: 0;
}

.investments-main__aside {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.yield-chart-card__desc {
  margin: -8px 18px 12px;
  font-size: 0.82rem;
  color: var(--color-text-muted);
  line-height: 1.45;
}

.yield-chart-card :deep(.chart-wrap) {
  padding: 0 18px 18px;
}

/* Métricas (50% da linha superior) */
.metrics-panel {
  display: flex;
  flex-direction: column;
  gap: 12px;
  min-width: 0;
  height: 100%;
}

.metrics-grid--row {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px;
}

@media (max-width: 640px) {
  .metrics-grid--row {
    grid-template-columns: 1fr;
  }
}

.metric-card {
  background: var(--color-bg-secondary);
  border: 1px solid var(--color-border-dark);
  border-radius: var(--radius-md);
  padding: 14px 16px;
  display: flex;
  flex-direction: column;
  gap: 6px;
  min-width: 0;
}

.metric-card--highlight {
  border-color: var(--color-accent);
  background: rgba(197, 119, 0, 0.12);
}

.metric-card__label {
  font-size: 0.78rem;
  color: var(--color-text-muted);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.metric-card__value {
  font-size: 1.05rem;
  font-weight: 700;
  color: var(--color-text);
  word-break: break-word;
}

/* Câmbio (50% da linha superior) */
.forex-panel {
  margin-bottom: 0;
  padding: 20px 22px;
  min-width: 0;
  height: 100%;
  display: flex;
  flex-direction: column;
  border: 1px solid var(--color-info);
  background: linear-gradient(
    135deg,
    rgba(37, 55, 98, 0.45) 0%,
    var(--color-bg-secondary) 55%
  );
}

.forex-panel__head {
  margin-bottom: 16px;
}

.forex-panel__title {
  font-size: 1.1rem;
  font-weight: 700;
  margin: 0 0 6px;
}

.forex-panel__subtitle {
  margin: 0;
  font-size: 0.88rem;
  color: var(--color-text-muted);
}

.forex-panel__subtitle strong {
  color: var(--color-accent);
}

.forex-panel__loading,
.forex-panel__error {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 0.9rem;
  color: var(--color-text-muted);
}

.forex-panel__error {
  color: var(--color-error-text);
}

.forex-panel__error :deep(svg) {
  fill: currentColor;
}

.forex-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 14px;
  flex: 1;
  align-content: center;
}

@media (max-width: 640px) {
  .forex-grid {
    grid-template-columns: 1fr;
  }
}

.forex-item {
  background: var(--color-bg-primary);
  border: 1px solid var(--color-border-light);
  border-radius: var(--radius-sm);
  padding: 14px 16px;
  text-align: center;
}

.forex-item__label {
  display: block;
  font-size: 0.8rem;
  color: var(--color-text-muted);
  margin-bottom: 6px;
}

.forex-item__value {
  font-size: 1.25rem;
  font-weight: 700;
  color: var(--color-text);
}

/* Formulário e histórico */
.section-title {
  font-size: 1rem;
  font-weight: 700;
  margin: 0 0 16px;
  padding: 16px 18px 0;
}

.entry-form-card,
.history-card {
  margin-bottom: 0;
}

.history-card {
  min-width: 0;
}

.history-card .table-wrap {
  width: 100%;
}

.history-card .table {
  width: 100%;
}

.entry-form {
  padding: 0 18px 18px;
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

.entry-form__actions {
  margin-top: 14px;
}

.entry-form-card .section-title {
  padding-bottom: 0;
}

.table-wrap {
  overflow-x: auto;
}

.history-card .section-title {
  padding-bottom: 0;
}

.table-empty {
  text-align: center;
  color: var(--color-text-muted);
  padding: 28px !important;
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
  transition: background 0.15s, border-color 0.15s;
}

.btn-icon:hover:not(:disabled) {
  border-color: var(--color-error);
  background: var(--color-error-bg);
}

.btn-icon--danger :deep(svg) {
  fill: var(--color-error-text);
}

.btn-icon:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
</style>
