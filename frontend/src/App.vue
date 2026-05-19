<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()

const navLinks = [
  { to: '/',           label: 'Dashboard',  icon: 'chart-pie' },
  { to: '/categorias', label: 'Categorias', icon: 'tag-alt'   },
  { to: '/extrato',    label: 'Extrato',    icon: 'file-alt'  },
  { to: '/importar',   label: 'Importar',   icon: 'upload-alt'},
]

const isActive = (path) =>
  path === '/' ? route.path === '/' : route.path.startsWith(path)

const pageTitle = computed(() => route.meta?.title ?? 'Finanças')
</script>

<template>
  <div class="app-shell">
    <aside class="sidebar">
      <div class="sidebar-header">
        <unicon name="wallet" class="sidebar-logo-icon" />
        <h1 class="sidebar-title">Finanças</h1>
      </div>

      <nav class="sidebar-nav">
        <RouterLink
          v-for="link in navLinks"
          :key="link.to"
          :to="link.to"
          class="nav-link"
          :class="{ 'nav-link--active': isActive(link.to) }"
        >
          <unicon :name="link.icon" class="nav-link__icon" />
          <span class="nav-link__label">{{ link.label }}</span>
        </RouterLink>
      </nav>

      <footer class="sidebar-footer">
        <span class="sidebar-version">v1.0.0</span>
      </footer>
    </aside>

    <div class="main-wrapper">
      <header class="topbar">
        <h2 class="topbar-title">{{ pageTitle }}</h2>
      </header>

      <main class="main-content">
        <RouterView />
      </main>
    </div>
  </div>
</template>

<style scoped>
.app-shell {
  display: flex;
  min-height: 100vh;
  background: var(--color-bg-primary);
}

/* Sidebar = secondary */
.sidebar {
  width: 220px;
  min-height: 100vh;
  background: var(--color-bg-secondary);
  display: flex;
  flex-direction: column;
  position: fixed;
  top: 0;
  left: 0;
  bottom: 0;
  z-index: 100;
  border-right: 1px solid var(--color-border-dark);
  box-shadow: var(--shadow-sidebar);
}

.sidebar-header {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 22px 20px 18px;
  border-bottom: 1px solid var(--color-border-dark);
}

.sidebar-logo-icon {
  width: 28px;
  height: 28px;
  flex-shrink: 0;
}

.sidebar-title {
  font-size: 1.05rem;
  font-weight: 700;
  color: var(--color-text);
}

.sidebar-nav {
  flex: 1;
  padding: 16px 12px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.nav-link {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  border-radius: var(--radius-sm);
  text-decoration: none;
  color: var(--color-text-muted);
  font-size: .9rem;
  font-weight: 500;
  border: 1px solid transparent;
  transition: background .15s, color .15s, border-color .15s;
}

.nav-link:hover {
  border-bottom: 1px solid var(--color-accent);
}

.nav-link--active {
  color: var(--color-accent);
  box-shadow: inset 3px 0 0 var(--color-accent);
}

.nav-link__icon {
  width: 18px;
  height: 18px;
  flex-shrink: 0;
}

.nav-link--active :deep(svg) {
  fill: var(--color-accent);
}

.sidebar-footer {
  padding: 14px 20px;
  border-top: 1px solid var(--color-border-dark);
  font-size: .72rem;
  color: var(--color-text-muted);
}

/* Main = primary */
.main-wrapper {
  margin-left: 220px;
  flex: 1;
  min-width: 0;
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  background: var(--color-bg-primary);
}

.topbar {
  height: 56px;
  background: var(--color-bg-primary);
  display: flex;
  align-items: center;
  padding: 0 32px;
  position: sticky;
  top: 0;
  z-index: 50;
}

.topbar-title {
  font-size: 1.15rem;
  font-weight: 600;
  color: var(--color-text);
  margin: 0;
}

.main-content {
  flex: 1;
  padding: 28px 32px;
  background: var(--color-bg-primary);
  color: var(--color-text);
}
</style>
