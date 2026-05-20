import { ref, watch } from 'vue'

const STORAGE_KEY = 'manubank_sidebar_collapsed'

export function useSidebar() {
  const collapsed = ref(localStorage.getItem(STORAGE_KEY) === '1')

  watch(collapsed, (value) => {
    localStorage.setItem(STORAGE_KEY, value ? '1' : '0')
  })

  function toggle() {
    collapsed.value = !collapsed.value
  }

  return { collapsed, toggle }
}
