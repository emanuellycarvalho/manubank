import { createRouter, createWebHistory } from 'vue-router'
import Dashboard    from '@/views/Dashboard.vue'
import Categories   from '@/views/Categories.vue'
import Rules        from '@/views/Rules.vue'
import Transactions from '@/views/Transactions.vue'
import Import       from '@/views/Import.vue'

const routes = [
  {
    path: '/',
    name: 'Dashboard',
    component: Dashboard,
    meta: { title: 'Dashboard' },
  },
  {
    path: '/categorias',
    name: 'Categories',
    component: Categories,
    meta: { title: 'Categorias' },
  },
  {
    path: '/regras',
    name: 'Rules',
    component: Rules,
    meta: { title: 'Regras' },
  },
  {
    path: '/extrato',
    name: 'Transactions',
    component: Transactions,
    meta: { title: 'Extrato' },
  },
  {
    path: '/importar',
    name: 'Import',
    component: Import,
    meta: { title: 'Importar' },
  },
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
})

router.afterEach((to) => {
  document.title = `${to.meta.title ?? 'ManuBank'} — ManuBank`
})

export default router
