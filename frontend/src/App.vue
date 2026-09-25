<script setup>
import { ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useAuth } from './stores/auth'
import { resources } from './resources'
import { theme, toggleTheme } from './theme'
import logo from './assets/logo-imm-negatif.svg'
import UrgenceBar from './components/UrgenceBar.vue'

const auth = useAuth()
const route = useRoute()
const open = ref(false)

// Referme le menu (mobile) à chaque navigation.
watch(() => route.fullPath, () => (open.value = false))
</script>

<template>
  <div class="bg" aria-hidden="true"></div>

  <div class="shell" :class="{ open }">
    <aside id="menu" class="sidebar" aria-label="Navigation principale">
      <RouterLink to="/" class="brand">
        <img :src="logo" alt="IMM – Institut Montsouris" class="logo" />
        <span>Annuaire des gardes</span>
      </RouterLink>

      <nav class="nav">
        <p class="nav-title">Navigation</p>
        <RouterLink to="/" class="nav-item" exact-active-class="active">Annuaire du personnel</RouterLink>

        <template v-if="auth.isAdmin">
          <p class="nav-title">Administration</p>
          <RouterLink
            v-for="(r, key) in resources"
            :key="key"
            :to="{ name: 'admin', params: { resource: key } }"
            class="nav-item"
            active-class="active"
          >
            {{ r.title }}
          </RouterLink>
        </template>
      </nav>

      <div class="side-foot">
        <button
          class="side-btn"
          :aria-label="theme === 'dark' ? 'Passer en mode clair' : 'Passer en mode sombre'"
          @click="toggleTheme"
        >
          {{ theme === 'dark' ? '☀ Mode clair' : '☾ Mode sombre' }}
        </button>
        <button v-if="auth.isAdmin" class="side-btn" @click="auth.logout()">Déconnexion</button>
        <RouterLink v-else to="/connexion" class="side-btn" active-class="active">Connexion</RouterLink>
      </div>
    </aside>

    <div class="scrim" @click="open = false"></div>

    <div class="main">
      <header class="mobilebar">
        <button class="burger" aria-controls="menu" :aria-expanded="open" aria-label="Menu" @click="open = !open">☰</button>
        <img :src="logo" alt="IMM – Institut Montsouris" class="logo" />
      </header>
      <UrgenceBar />
      <main class="container">
        <RouterView />
      </main>
    </div>
  </div>
</template>
