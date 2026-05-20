<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'

const props = defineProps({
  start: { type: String, default: '' },
  end:   { type: String, default: '' },
  label: { type: String, default: 'Período' },
})

const emit = defineEmits(['update:dateRange'])

const isOpen = ref(false)
const draftStart = ref(props.start)
const draftEnd   = ref(props.end)
const rootRef    = ref(null)

const QUICK_LINKS = [
  { id: 'today',      label: 'Hoje' },
  { id: 'last7',      label: 'Últimos 7 dias' },
  { id: 'last30',     label: 'Últimos 30 dias' },
  { id: 'thisMonth',  label: 'Mês atual' },
  { id: 'lastMonth',  label: 'Mês passado' },
  { id: 'thisYear',   label: 'Este ano' },
  { id: 'all',        label: 'Tudo' },
]

function toIsoDate(date) {
  const y = date.getFullYear()
  const m = String(date.getMonth() + 1).padStart(2, '0')
  const d = String(date.getDate()).padStart(2, '0')
  return `${y}-${m}-${d}`
}

function addDays(date, days) {
  const d = new Date(date)
  d.setDate(d.getDate() + days)
  return d
}

function startOfMonth(date) {
  return new Date(date.getFullYear(), date.getMonth(), 1)
}

function endOfMonth(date) {
  return new Date(date.getFullYear(), date.getMonth() + 1, 0)
}

function computePreset(id) {
  const today = new Date()
  today.setHours(0, 0, 0, 0)

  switch (id) {
    case 'today':
      return { start: toIsoDate(today), end: toIsoDate(today) }
    case 'last7':
      return { start: toIsoDate(addDays(today, -6)), end: toIsoDate(today) }
    case 'last30':
      return { start: toIsoDate(addDays(today, -29)), end: toIsoDate(today) }
    case 'thisMonth':
      return {
        start: toIsoDate(startOfMonth(today)),
        end: toIsoDate(endOfMonth(today)),
      }
    case 'lastMonth': {
      const prev = new Date(today.getFullYear(), today.getMonth() - 1, 1)
      return {
        start: toIsoDate(startOfMonth(prev)),
        end: toIsoDate(endOfMonth(prev)),
      }
    }
    case 'thisYear':
      return {
        start: toIsoDate(new Date(today.getFullYear(), 0, 1)),
        end: toIsoDate(today),
      }
    case 'all':
      return { start: '2000-01-01', end: toIsoDate(today) }
    default:
      return { start: '', end: '' }
  }
}

function fmtDisplay(iso) {
  if (!iso) return '—'
  const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(iso)
  if (!match) return iso
  return `${match[3]}/${match[2]}/${match[1]}`
}

const triggerLabel = computed(() => {
  if (props.start && props.end) {
    return `${fmtDisplay(props.start)} – ${fmtDisplay(props.end)}`
  }
  return 'Selecionar período'
})

function toggleOpen() {
  if (!isOpen.value) {
    draftStart.value = props.start || draftStart.value
    draftEnd.value   = props.end || draftEnd.value
  }
  isOpen.value = !isOpen.value
}

function close() {
  isOpen.value = false
}

function onQuickLink(id) {
  const range = computePreset(id)
  draftStart.value = range.start
  draftEnd.value   = range.end
}

function apply() {
  if (!draftStart.value || !draftEnd.value) return
  if (draftStart.value > draftEnd.value) return

  emit('update:dateRange', {
    start: draftStart.value,
    end: draftEnd.value,
  })
  close()
}

function onClickOutside(e) {
  if (!isOpen.value || !rootRef.value) return
  if (!rootRef.value.contains(e.target)) close()
}

function onKeydown(e) {
  if (e.key === 'Escape') close()
}

onMounted(() => {
  document.addEventListener('click', onClickOutside)
  document.addEventListener('keydown', onKeydown)
})

onUnmounted(() => {
  document.removeEventListener('click', onClickOutside)
  document.removeEventListener('keydown', onKeydown)
})
</script>

<template>
  <div ref="rootRef" class="date-range-picker">
    <button
      type="button"
      class="drp-trigger"
      :aria-expanded="isOpen"
      aria-haspopup="dialog"
      @click.stop="toggleOpen"
    >
      <span class="drp-trigger__label">{{ label }}</span>
      <span class="drp-trigger__value">{{ triggerLabel }}</span>
      <unicon name="angle-down" width="16" height="16" class="drp-trigger__icon" />
    </button>

    <Transition name="drp-pop">
      <div
        v-if="isOpen"
        class="drp-popover panel"
        role="dialog"
        aria-label="Selecionar intervalo de datas"
        @click.stop
      >
        <div class="drp-popover__grid">
          <nav class="drp-quick" aria-label="Atalhos de período">
            <button
              v-for="link in QUICK_LINKS"
              :key="link.id"
              type="button"
              class="drp-quick__item"
              @click="onQuickLink(link.id)"
            >
              {{ link.label }}
            </button>
          </nav>

          <div class="drp-custom">
            <p class="drp-custom__title">Personalizado</p>
            <label class="drp-field">
              <span class="drp-field__label">Data inicial</span>
              <input
                v-model="draftStart"
                type="date"
                class="form-control drp-field__input"
              />
            </label>
            <label class="drp-field">
              <span class="drp-field__label">Data final</span>
              <input
                v-model="draftEnd"
                type="date"
                class="form-control drp-field__input"
                :min="draftStart"
              />
            </label>
            <button
              type="button"
              class="btn btn--primary drp-custom__apply"
              :disabled="!draftStart || !draftEnd || draftStart > draftEnd"
              @click="apply"
            >
              Aplicar
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.date-range-picker {
  position: relative;
  display: inline-block;
}

.drp-trigger {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  padding: 8px 12px;
  min-width: 220px;
  background: var(--color-bg-input);
  border: 1px solid var(--color-border-light);
  border-radius: var(--radius-sm);
  color: var(--color-text);
  font-family: inherit;
  font-size: 0.88rem;
  cursor: pointer;
  transition: border-color 0.15s, box-shadow 0.15s;
  text-align: left;
}

.drp-trigger:hover {
  border-color: var(--color-text);
}

.drp-trigger[aria-expanded='true'] {
  border-color: var(--color-accent);
  box-shadow: 0 0 0 2px rgba(242, 76, 0, 0.35);
}

.drp-trigger__label {
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.4px;
  color: var(--color-text-muted);
}

.drp-trigger__value {
  flex: 1;
  font-weight: 600;
}

.drp-trigger__icon {
  flex-shrink: 0;
  opacity: 0.85;
}

.drp-popover {
  position: absolute;
  top: calc(100% + 8px);
  left: 0;
  z-index: 400;
  min-width: 420px;
  padding: 0;
  overflow: hidden;
}

.drp-popover__grid {
  display: grid;
  grid-template-columns: 160px 1fr;
}

.drp-quick {
  display: flex;
  flex-direction: column;
  padding: 8px 0;
  border-right: 1px solid var(--color-border-subtle-dark);
  background: var(--color-bg-secondary);
}

.drp-quick__item {
  display: block;
  width: 100%;
  padding: 9px 14px;
  border: none;
  background: transparent;
  color: var(--color-text);
  font-family: inherit;
  font-size: 0.86rem;
  text-align: left;
  cursor: pointer;
  transition: background 0.12s;
}

.drp-quick__item:hover {
  background: var(--color-bg-elevated);
  color: var(--color-accent-hover);
}

.drp-custom {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 16px;
}

.drp-custom__title {
  margin: 0;
  font-size: 0.78rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: var(--color-text-muted);
}

.drp-field {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.drp-field__label {
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--color-text-muted);
}

.drp-field__input {
  width: 100%;
  color-scheme: dark;
}

.drp-custom__apply {
  align-self: flex-end;
  margin-top: 4px;
}

.drp-pop-enter-active,
.drp-pop-leave-active {
  transition: opacity 0.15s ease, transform 0.15s ease;
}

.drp-pop-enter-from,
.drp-pop-leave-to {
  opacity: 0;
  transform: translateY(-6px);
}

@media (max-width: 520px) {
  .drp-popover {
    min-width: min(100vw - 32px, 420px);
  }

  .drp-popover__grid {
    grid-template-columns: 1fr;
  }

  .drp-quick {
    flex-direction: row;
    flex-wrap: wrap;
    border-right: none;
    border-bottom: 1px solid var(--color-border-subtle-dark);
    padding: 8px;
    gap: 4px;
  }

  .drp-quick__item {
    width: auto;
    padding: 6px 10px;
    border-radius: var(--radius-sm);
  }
}
</style>
