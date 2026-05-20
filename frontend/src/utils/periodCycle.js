/**
 * Ciclo financeiro mensal (mesmo critério do Extrato / Transactions.vue).
 * Chave YYYY-MM = mês de referência; o intervalo vai do dia de início
 * (último dia útil) no mês anterior até o dia anterior ao início no mês da chave.
 */

import { ref } from 'vue'
import { toIsoDate, toMonthYear, monthsBetween, shiftMonthYear } from '@/utils/dates.js'

export const FINANCE_PERIOD_START_DAY_KEY = 'finance_period_start_day'

const DEFAULT_PERIOD_START_DAY = 29

/** Estado reativo do dia de início (sincroniza Extrato ↔ Investimentos). */
export const financePeriodStartDay = ref(readPeriodStartDayFromStorage())

function readPeriodStartDayFromStorage() {
  const raw = parseInt(
    localStorage.getItem(FINANCE_PERIOD_START_DAY_KEY) ?? String(DEFAULT_PERIOD_START_DAY),
    10,
  )
  return Math.min(28, Math.max(1, Number.isNaN(raw) ? DEFAULT_PERIOD_START_DAY : raw))
}

/** Dia de início do ciclo (1–28), persistido no localStorage. */
export function getFinancePeriodStartDay() {
  return financePeriodStartDay.value
}

/** Persiste e notifica listeners na mesma aba. */
export function setFinancePeriodStartDay(day) {
  const clamped = Math.min(28, Math.max(1, day))
  financePeriodStartDay.value = clamped
  localStorage.setItem(FINANCE_PERIOD_START_DAY_KEY, String(clamped))
}

/** Último dia útil em ou antes de year/month(1–12)/day. */
export function lastBusinessDay(year, month1Based, day) {
  const daysInMonth = new Date(year, month1Based, 0).getDate()
  const d = Math.min(day, daysInMonth)
  const dt = new Date(year, month1Based - 1, d)
  const dow = dt.getDay()

  if (dow === 6) dt.setDate(dt.getDate() - 1)
  if (dow === 0) dt.setDate(dt.getDate() - 2)

  return dt
}

/**
 * Intervalo ISO do ciclo YYYY-MM.
 *
 * @returns {{ start: string, end: string }}
 */
export function getCycleBounds(monthYear, periodStartDay = getFinancePeriodStartDay()) {
  const [y, m] = monthYear.split('-').map(Number)
  const start = lastBusinessDay(y, m - 1, periodStartDay)
  const end = lastBusinessDay(y, m, periodStartDay - 1)

  return { start: toIsoDate(start), end: toIsoDate(end) }
}

/** Chave YYYY-MM do ciclo em que a data ISO cai. */
export function getCycleKeyForDate(isoDate, periodStartDay = getFinancePeriodStartDay()) {
  if (!isoDate || !/^\d{4}-\d{2}-\d{2}$/.test(isoDate)) {
    return isoDate?.slice(0, 7) ?? ''
  }

  const [y, m] = isoDate.split('-').map(Number)
  const base = toMonthYear(y, m)
  const candidates = [
    shiftMonthYear(base, -2),
    shiftMonthYear(base, -1),
    base,
    shiftMonthYear(base, 1),
    shiftMonthYear(base, 2),
  ]

  for (const key of candidates) {
    const { start, end } = getCycleBounds(key, periodStartDay)
    if (isoDate >= start && isoDate <= end) return key
  }

  return base
}

/** Lista chaves de ciclo entre duas datas ISO (inclusive). */
export function listCycleKeysBetween(startIso, endIso, periodStartDay = getFinancePeriodStartDay()) {
  if (!startIso || !endIso || startIso > endIso) return []

  const startKey = getCycleKeyForDate(startIso, periodStartDay)
  const endKey = getCycleKeyForDate(endIso, periodStartDay)

  return monthsBetween(startKey, endKey)
}

function fmtShort(dt) {
  return `${String(dt.getDate()).padStart(2, '0')}/${String(dt.getMonth() + 1).padStart(2, '0')}`
}

/** Rótulo do ciclo para eixos e UI (ex.: 29/02 – 28/03). */
export function formatCycleLabel(monthYear, periodStartDay = getFinancePeriodStartDay()) {
  if (!monthYear) return ''
  const { start, end } = getCycleBounds(monthYear, periodStartDay)
  const startDt = new Date(`${start}T12:00:00`)
  const endDt = new Date(`${end}T12:00:00`)

  return `${fmtShort(startDt)} – ${fmtShort(endDt)}`
}
