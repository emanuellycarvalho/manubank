<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useSidebar } from '@/composables/useSidebar.js'
import { useProfile } from '@/composables/useProfile.js'

const route = useRoute()
const { collapsed: sidebarCollapsed, toggle: toggleSidebar } = useSidebar()
const {
  profilePhoto,
  profileName,
  profileError,
  onFileChange,
  removePhoto,
  saveProfileName,
} = useProfile()

const brandLogoSrc = '/manubank-logo-1.png'

const navLinks = [
  { to: '/',           label: 'Dashboard',  icon: 'chart-pie' },
  { to: '/categorias', label: 'Categorias', icon: 'tag-alt' },
  { to: '/regras',     label: 'Regras',     icon: 'clipboard-alt' },
  { to: '/extrato',    label: 'Extrato',    icon: 'file-alt' },
  { to: '/importar',   label: 'Importar',   icon: 'upload-alt' },
]

const isActive = (path) =>
  path === '/' ? route.path === '/' : route.path.startsWith(path)

const pageTitle = computed(() => route.meta?.title ?? 'ManuBank')
</script>

<template>
  <div
    class="app-shell"
    :class="{ 'app-shell--sidebar-collapsed': sidebarCollapsed }"
  >
    <aside
      class="sidebar"
      :class="{ 'sidebar--collapsed': sidebarCollapsed }"
      :aria-expanded="!sidebarCollapsed"
    >
      <div class="sidebar-header">
        <button
          type="button"
          class="sidebar-toggle"
          :title="sidebarCollapsed ? 'Expandir menu' : 'Recolher menu'"
          :aria-label="sidebarCollapsed ? 'Expandir menu' : 'Recolher menu'"
          @click="toggleSidebar"
        >
          <unicon :name="sidebarCollapsed ? 'angle-right-b' : 'angle-left-b'" width="20" height="20" />
        </button>

        <div v-show="!sidebarCollapsed" class="sidebar-brand">
          <img :src="brandLogoSrc" alt="" class="sidebar-brand-logo" width="40" height="40" />
          <h1 class="sidebar-title">ManuBank</h1>
        </div>

        <img
          v-show="sidebarCollapsed"
          :src="brandLogoSrc"
          alt="ManuBank"
          class="sidebar-brand-logo sidebar-brand-logo--solo"
          width="28"
          height="28"
        />
      </div>

      <section class="sidebar-profile" aria-label="Perfil">
        <label
          class="profile-avatar"
          :title="sidebarCollapsed ? 'Alterar foto' : 'Clique para alterar a foto de perfil'"
        >
          <input
            type="file"
            accept="image/jpeg,image/png,image/webp,image/gif"
            class="visually-hidden"
            @change="onFileChange"
          />
          <img
            v-if="profilePhoto"
            :src="profilePhoto"
            alt="Foto de perfil"
            class="profile-avatar__img"
          />
          <span v-else class="profile-avatar__placeholder" aria-hidden="true">
            <unicon name="user" width="22" height="22" />
          </span>
        </label>

        <div v-show="!sidebarCollapsed" class="profile-meta">
          <input
            v-model="profileName"
            type="text"
            class="profile-name-input"
            placeholder="Seu nome"
            maxlength="40"
            aria-label="Nome do perfil"
            @blur="saveProfileName"
            @keydown.enter="$event.target.blur()"
          />
          <div class="profile-meta__actions">
            <span v-if="!profilePhoto" class="profile-meta__hint">Clique na foto para adicionar</span>
            <button
              v-else
              type="button"
              class="profile-meta__remove"
              @click="removePhoto"
            >
              Remover foto
            </button>
          </div>
          <span v-if="profileError" class="profile-meta__error">{{ profileError }}</span>
        </div>
      </section>

      <nav class="sidebar-nav" aria-label="Navegação principal">
        <RouterLink
          v-for="link in navLinks"
          :key="link.to"
          :to="link.to"
          class="nav-link"
          :class="{ 'nav-link--active': isActive(link.to) }"
          :title="sidebarCollapsed ? link.label : undefined"
        >
          <unicon :name="link.icon" class="nav-link__icon" />
          <span v-show="!sidebarCollapsed" class="nav-link__label">{{ link.label }}</span>
        </RouterLink>
      </nav>

      <footer class="sidebar-footer">
        <span v-show="!sidebarCollapsed" class="sidebar-version">v1.0.0</span>
      </footer>
    </aside>

    <div class="main-wrapper">
      <header class="topbar">
        <button
          type="button"
          class="topbar-toggle"
          :title="sidebarCollapsed ? 'Expandir menu' : 'Recolher menu'"
          :aria-label="sidebarCollapsed ? 'Expandir menu' : 'Recolher menu'"
          @click="toggleSidebar"
        >
          <unicon name="bars" width="20" height="20" />
        </button>
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
  --sidebar-width: 220px;
  --sidebar-width-collapsed: 68px;

  display: flex;
  min-height: 100vh;
  background: var(--color-bg-primary);
}

.sidebar {
  width: var(--sidebar-width);
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
  transition: width 0.22s ease;
}

.sidebar--collapsed {
  width: var(--sidebar-width-collapsed);
}

.sidebar-header {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 16px 12px;
  border-bottom: 1px solid var(--color-border-dark);
  min-height: 60px;
}

.sidebar--collapsed .sidebar-header {
  flex-direction: column;
  justify-content: center;
  padding: 12px 8px;
  gap: 10px;
}

.sidebar-toggle {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  flex-shrink: 0;
  border: 1px solid transparent;
  background: transparent;
  color: var(--color-text-muted);
  cursor: pointer;
  transition: background 0.15s, color 0.15s, border-color 0.15s;
}

.sidebar-toggle .unicon svg:hover {
  fill: var(--color-accent);
}

.sidebar-brand {
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
  flex: 1;
}

.sidebar-brand-logo {
  width: 40px;
  height: 40px;
  flex-shrink: 0;
  object-fit: contain;
  display: block;
}

.sidebar-brand-logo--solo {
  margin: 0 auto;
}

.sidebar-title {
  font-size: 1.05rem;
  font-weight: 700;
  color: var(--color-text);
  margin: 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* Profile — abaixo da marca, antes do menu */
.sidebar-profile {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px;
  margin: 0 10px;
  border-bottom: 1px solid var(--color-border-dark);
}

.sidebar--collapsed .sidebar-profile {
  flex-direction: column;
  margin: 0 8px;
  padding: 12px 8px;
  gap: 0;
  justify-content: center;
}

.profile-avatar {
  position: relative;
  width: 44px;
  height: 44px;
  flex-shrink: 0;
  border-radius: 50%;
  overflow: hidden;
  cursor: pointer;
  border: 2px solid var(--color-border-light);
  transition: border-color 0.15s, box-shadow 0.15s;
}

.profile-avatar:hover {
  border-color: var(--color-accent);
  box-shadow: 0 0 0 2px rgba(197, 119, 0, 0.25);
}

.profile-avatar__img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.profile-avatar__placeholder {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  height: 100%;
  background: var(--color-bg-elevated);
  color: var(--color-text-muted);
}

.profile-meta {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 0;
  flex: 1;
}

.profile-name-input {
  width: 100%;
  margin: 0;
  border: 1px solid transparent;
  border-radius: var(--radius-sm);
  background: transparent;
  font-size: 1.2rem;
  font-weight: 600;
  color: var(--color-text);
  font-family: inherit;
}

.profile-name-input::placeholder {
  color: var(--color-text-muted);
  font-weight: 500;
  opacity: 0.65;
}

.profile-name-input:hover {
  border-color: var(--color-border-light);
  background: var(--color-bg-elevated);
}

.profile-name-input:focus {
  outline: none;
  border-color: var(--color-accent);
  background: var(--color-bg-elevated);
  box-shadow: 0 0 0 2px rgba(197, 119, 0, 0.25);
}

.profile-meta__actions {
  min-height: 1rem;
}

.profile-meta__hint {
  font-size: 0.7rem;
  color: var(--color-text-muted);
  opacity: 0.75;
}

.profile-meta__remove {
  padding: 0;
  border: none;
  background: none;
  font-size: 0.7rem;
  color: var(--color-accent);
  cursor: pointer;
  text-align: left;
  font-family: inherit;
}

.profile-meta__remove:hover {
  text-decoration: underline;
}

.profile-meta__error {
  font-size: 0.68rem;
  color: var(--color-error-text);
  line-height: 1.3;
}

.sidebar-nav {
  flex: 1;
  padding: 16px 10px;
  display: flex;
  flex-direction: column;
  gap: 4px;
  overflow-y: auto;
}

.sidebar--collapsed .sidebar-nav {
  padding: 16px 8px;
  align-items: center;
}

.nav-link {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  border-radius: var(--radius-sm);
  text-decoration: none;
  color: var(--color-text-muted);
  font-size: 0.9rem;
  font-weight: 500;
  border: 1px solid transparent;
  transition: background 0.15s, color 0.15s, border-color 0.15s;
}

.sidebar--collapsed .nav-link {
  justify-content: center;
  width: 44px;
  height: 44px;
  padding: 0;
}

.nav-link:hover {
  border-bottom: 1px solid var(--color-accent);
}

.sidebar--collapsed .nav-link:hover {
  border-bottom: none;
  background: var(--color-bg-elevated);
}

.nav-link--active {
  color: var(--color-accent);
  box-shadow: inset 3px 0 0 var(--color-accent);
}

.sidebar--collapsed .nav-link--active {
  box-shadow: none;
  background: rgba(197, 119, 0, 0.15);
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
  font-size: 0.72rem;
  color: var(--color-text-muted);
}

.sidebar--collapsed .sidebar-footer {
  padding: 10px 8px;
}

.main-wrapper {
  margin-left: var(--sidebar-width);
  flex: 1;
  min-width: 0;
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  background: var(--color-bg-primary);
  transition: margin-left 0.22s ease;
}

.app-shell--sidebar-collapsed .main-wrapper {
  margin-left: var(--sidebar-width-collapsed);
}

.topbar {
  height: 56px;
  background: var(--color-bg-primary);
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 0 32px;
  position: sticky;
  top: 0;
  z-index: 50;
}

.topbar-toggle {
  display: none;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  border: 1px solid var(--color-border-light);
  border-radius: var(--radius-sm);
  background: var(--color-bg-elevated);
  color: var(--color-text-muted);
  cursor: pointer;
}

.topbar-toggle:hover {
  color: var(--color-text);
  border-color: var(--color-accent);
}

@media (max-width: 768px) {
  .sidebar-toggle {
    display: none;
  }

  .topbar-toggle {
    display: inline-flex;
  }

  .sidebar {
    transform: translateX(0);
    transition: transform 0.22s ease, width 0.22s ease;
  }

  .sidebar--collapsed {
    transform: translateX(-100%);
    width: var(--sidebar-width);
  }

  .app-shell--sidebar-collapsed .main-wrapper,
  .main-wrapper {
    margin-left: 0;
  }
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
