import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

const KEY = 'annuaire_jwt'

function read() {
  try {
    return sessionStorage.getItem(KEY)
  } catch {
    return null
  }
}

export const useAuth = defineStore('auth', () => {
  const token = ref(read())
  const isAdmin = computed(() => !!token.value)

  function setToken(t) {
    token.value = t
    try {
      t ? sessionStorage.setItem(KEY, t) : sessionStorage.removeItem(KEY)
    } catch {
      /* stockage indisponible : le token reste en mémoire */
    }
  }

  async function login(username, password) {
    const { post } = await import('../api')
    const { data } = await post('/login', { username, password })
    setToken(data.token)
  }

  function logout() {
    setToken(null)
    import('../router').then(({ default: router }) => {
      if (router.currentRoute.value.meta.admin) router.push({ name: 'login' })
    })
  }

  return { token, isAdmin, login, logout }
})
