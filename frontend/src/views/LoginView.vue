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
  <section class="login">
    <h1>Connexion</h1>
    <form class="edit-card narrow" @submit.prevent="submit">
      <div class="edit-grid one">
        <label>
          <span class="edit-label">Identifiant</span>
          <input v-model="username" autocomplete="username" required autofocus />
        </label>
        <label>
          <span class="edit-label">Mot de passe</span>
          <input v-model="password" type="password" autocomplete="current-password" required />
        </label>
      </div>
      <p v-if="error" class="error" role="alert">{{ error }}</p>
      <button class="primary submit-full" :disabled="busy">{{ busy ? 'Connexion…' : 'Se connecter' }}</button>
    </form>
  </section>
</template>
