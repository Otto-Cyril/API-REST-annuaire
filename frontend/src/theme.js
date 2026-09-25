import { ref } from 'vue'

const KEY = 'annuaire_theme'

function initial() {
  try {
    const saved = localStorage.getItem(KEY)
    if (saved === 'light' || saved === 'dark') return saved
  } catch {
    /* stockage indisponible : on retombe sur la préférence système */
  }
  return matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
}

export const theme = ref(initial())

export function applyTheme() {
  document.documentElement.dataset.theme = theme.value
}

export function toggleTheme() {
  theme.value = theme.value === 'dark' ? 'light' : 'dark'
  applyTheme()
  try {
    localStorage.setItem(KEY, theme.value)
  } catch {
    /* le choix reste valable pour la session */
  }
}
