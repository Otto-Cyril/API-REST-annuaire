<script setup>
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuth } from '../stores/auth'

const auth = useAuth()
const route = useRoute()
const router = useRouter()

const username = ref('')
const password = ref('')
const error = ref('')
const busy = ref(false)

async function submit() {
  busy.value = true
  error.value = ''
  try {
    await auth.login(username.value, password.value)
    const target = String(route.query.redirect ?? '')
    router.push(target.startsWith('/admin') ? target : '/admin')
  } catch (e) {
    error.value = e.message
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <h1>Connexion</h1>
  <form class="form narrow" @submit.prevent="submit">
    <label>
      Identifiant
      <input v-model="username" autocomplete="username" required autofocus />
    </label>
    <label>
      Mot de passe
      <input v-model="password" type="password" autocomplete="current-password" required />
    </label>
    <p v-if="error" class="error">{{ error }}</p>
    <button class="primary" :disabled="busy">{{ busy ? 'Connexion…' : 'Se connecter' }}</button>
  </form>
</template>
