import { reactive } from 'vue'

// Notification unique et globale (affichée par App.vue) : « Enregistré », « Supprimé » avec, si besoin, un bouton d'action (annuler).
export const toast = reactive({ message: '', action: null })

let timer

export function hideToast() {
  clearTimeout(timer)
  toast.message = ''
  toast.action = null
}

// action : { label, run } facultatif ; le message reste plus longtemps quand il propose une action.
export function showToast(message, action = null) {
  clearTimeout(timer)
  toast.message = message
  toast.action = action
  timer = setTimeout(hideToast, action ? 8000 : 3500)
}
