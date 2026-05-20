import axios from 'axios'

/** Em desenvolvimento o Vite faz proxy de /api → PHP; em produção local tudo está no mesmo servidor. */
const http = axios.create({
  baseURL: import.meta.env.DEV ? '/api' : '',
  headers: { 'Content-Type': 'application/json' },
})

http.interceptors.response.use(
  (res) => res,
  (err) => {
    const message =
      err.response?.data?.error ?? err.message ?? 'Erro desconhecido'
    return Promise.reject(new Error(message))
  },
)

// ── Categories ──────────────────────────────────────────────────────────────
export const categoriesApi = {
  list: () => http.get('/api_categories.php'),
  get: (id) => http.get(`/api_categories.php?id=${id}`),
  create: (payload) => http.post('/api_categories.php', payload),
  update: (id, payload) => http.put(`/api_categories.php?id=${id}`, payload),
  remove: (id) => http.delete(`/api_categories.php?id=${id}`),
}

// ── Transactions ────────────────────────────────────────────────────────────
export const transactionsApi = {
  list: (monthYear) =>
    http.get('/api_transactions.php', {
      params: monthYear ? { month_year: monthYear } : {},
    }),
  availableMonths: () => http.get('/api_transactions.php', { params: { available_months: 1 } }),
  updateCategory: (id, categoryId) =>
    http.patch(`/api_transactions.php?id=${id}`, { category_id: categoryId }),
  create: (payload) => http.post('/api_transactions.php', payload),
  remove: (id) => http.delete(`/api_transactions.php?id=${id}`),
}

// ── Import ──────────────────────────────────────────────────────────────────
export const importApi = {
  upload: (file) => {
    const form = new FormData()
    form.append('file', file)
    return http.post('/import.php', form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
  },
  importText: (text, year) =>
    http.post('/import_text.php', { text, year }),
}

// ── Parsing Rules ───────────────────────────────────────────────────────────
export const rulesApi = {
  list: () => http.get('/api_rules.php'),
  create: (payload) => http.post('/api_rules.php', payload),
  update: (id, payload) => http.put(`/api_rules.php?id=${id}`, payload),
  remove: (id) => http.delete(`/api_rules.php?id=${id}`),
}

// ── Charts (dashboard aggregations) ─────────────────────────────────────────
export const chartsApi = {
  getSeries: ({ startDate, endDate, granularity }) =>
    http.get('/api_charts.php', {
      params: {
        start_date: startDate,
        end_date: endDate,
        granularity,
      },
    }),
  getExpensesByCategory: ({ startDate, endDate }) =>
    http.get('/api_charts.php', {
      params: {
        start_date: startDate,
        end_date: endDate,
        breakdown: 'category',
      },
    }),
  getFixedVsVariable: ({ startDate, endDate, granularity }) =>
    http.get('/api_charts.php', {
      params: {
        start_date: startDate,
        end_date: endDate,
        granularity,
        breakdown: 'fixed_variable',
      },
    }),
  getCategoryEvolution: ({ startDate, endDate, granularity, categoryId }) =>
    http.get('/api_charts.php', {
      params: {
        start_date: startDate,
        end_date: endDate,
        granularity,
        breakdown: 'category_evolution',
        category_id: categoryId,
      },
    }),
  getYieldGrowth: ({ startDate, endDate, granularity }) =>
    http.get('/api_charts.php', {
      params: {
        start_date: startDate,
        end_date: endDate,
        granularity,
        breakdown: 'yield_growth',
      },
    }),
}

// ── Closures ────────────────────────────────────────────────────────────────
export const closuresApi = {
  getSummary: (monthYear) =>
    http.get(`/api_closures.php?month_year=${monthYear}`),
  getSaved: (monthYear) =>
    http.get(`/api_closures.php?month_year=${monthYear}&saved=1`),
  save: (payload) =>
    http.post('/api_closures.php', payload),
}

// ── Investments ─────────────────────────────────────────────────────────────
export const investmentsApi = {
  list: () => http.get('/api_investments.php'),
  createObjective: (payload) =>
    http.post('/api_investments.php', { action: 'create_objective', ...payload }),
  updateObjective: (payload) =>
    http.post('/api_investments.php', { action: 'update_objective', ...payload }),
  removeObjective: (objectiveId) =>
    http.delete(`/api_investments.php?objective_id=${objectiveId}`),
  addEntry: (payload) =>
    http.post('/api_investments.php', { action: 'add_entry', ...payload }),
  updateEntry: (payload) =>
    http.post('/api_investments.php', { action: 'update_entry', ...payload }),
  removeEntry: (entryId) =>
    http.delete(`/api_investments.php?entry_id=${entryId}`),
}

// ── Investment allocations (consolidado) ────────────────────────────────────
export const allocationsApi = {
  list: () => http.get('/api_allocations.php'),
  get: (id) => http.get(`/api_allocations.php?id=${id}`),
  create: (payload) => http.post('/api_allocations.php', payload),
  update: (id, payload) => http.put(`/api_allocations.php?id=${id}`, payload),
  remove: (id) => http.delete(`/api_allocations.php?id=${id}`),
}

// ── CDI base rate ───────────────────────────────────────────────────────────
export const cdiApi = {
  get: (refresh = false) =>
    http.get('/api_cdi.php', refresh ? { params: { refresh: 1 } } : {}),
}

// ── Reimbursements ──────────────────────────────────────────────────────────
export const reimbursementsApi = {
  activeClaims: () => http.get('/api_reimbursements.php'),
  dashboardSummary: () =>
    http.get('/api_reimbursements.php', { params: { summary: 1 } }),
  createClaim: (payload) =>
    http.post('/api_reimbursements.php', { action: 'create_claim', ...payload }),
  registerPayment: (payload) =>
    http.post('/api_reimbursements.php', { action: 'register_payment', ...payload }),
}

export default http
