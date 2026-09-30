<script setup>
import { ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useAuth } from './stores/auth'
import { resources } from './resources'
import { buildNavGroups, activeGroupId } from './menu'
import { toast, hideToast } from './toast'
import { theme, toggleTheme } from './theme'
import logo from './assets/logo-imm-negatif.svg'
import UrgenceBar from './components/UrgenceBar.vue'
import Icon from './components/Icon.vue'

const auth = useAuth()
const route = useRoute()
const open = ref(false)

// Entrées du menu « Administration » : toutes les ressources sauf celles marquées hideFromMenu, regroupées (voir menu.js).
const menuResources = Object.fromEntries(Object.entries(resources).filter(([, r]) => !r.hideFromMenu))
const navGroups = buildNavGroups(menuResources)

// Groupes dépliés : celui de la page affichée s'ouvre à chaque navigation, les autres restent au choix de l'utilisateur.
// L'état est mémorisé entre deux visites (localStorage, avec repli si le stockage est indisponible).
const GROUPS_KEY = 'annuaire_nav_groups'
function readGroups() {
  try {
    const saved = JSON.parse(localStorage.getItem(GROUPS_KEY))
    return Array.isArray(saved) ? saved.filter((id) => navGroups.some((g) => g.id === id)) : []
  } catch {
    return []
  }
}
const openGroups = ref(readGroups())
watch(openGroups, (ids) => {
  try {
    localStorage.setItem(GROUPS_KEY, JSON.stringify(ids))
  } catch {
    /* stockage indisponible : l'état reste en mémoire */
  }
})
const isOpen = (id) => openGroups.value.includes(id)
const toggleGroup = (id) => (openGroups.value = isOpen(id) ? openGroups.value.filter((g) => g !== id) : [...openGroups.value, id])

// Bouton de la notification : exécute l'action puis referme le message.
async function runToastAction() {
  const run = toast.action?.run
  hideToast()
  await run?.()
}
function openActiveGroup() {
  const id = activeGroupId(navGroups, route)
  if (id && !isOpen(id)) openGroups.value = [...openGroups.value, id]
}
openActiveGroup()

// Le lien d'évitement place le focus sur le contenu (un simple #ancre ne suffit pas avec le routeur).
const focusContent = () => document.getElementById('contenu')?.focus()

// Referme le menu (mobile) à chaque navigation.
watch(() => route.fullPath, () => {
  open.value = false
  openActiveGroup()
})
</script>

<template>
  <a href="#contenu" class="skip-link" @click.prevent="focusContent">Aller au contenu</a>
  <div class="bg" aria-hidden="true">
    <svg class="bg-decor" viewBox="0 0 1600 900" preserveAspectRatio="xMidYMid slice" focusable="false">
      <defs>
        <radialGradient id="decor-a"><stop offset="0" style="stop-color: var(--accent); stop-opacity: .55" /><stop offset="1" style="stop-color: var(--accent); stop-opacity: 0" /></radialGradient>
        <radialGradient id="decor-b"><stop offset="0" style="stop-color: var(--lavande); stop-opacity: .5" /><stop offset="1" style="stop-color: var(--lavande); stop-opacity: 0" /></radialGradient>
      </defs>
      <circle cx="1420" cy="110" r="460" fill="url(#decor-a)" />
      <circle cx="90" cy="800" r="430" fill="url(#decor-b)" />
      <path class="decor-wave-1" d="M0 640 C260 560 560 760 880 660 S1420 540 1600 620 V900 H0Z" />
      <path class="decor-wave-2" d="M0 730 C330 670 690 830 1010 750 S1450 680 1600 740 V900 H0Z" />
      <path class="decor-wave-3" d="M0 820 C380 780 720 890 1080 830 S1480 800 1600 830 V900 H0Z" />
    </svg>
  </div>

  <div class="shell" :class="{ open }">
    <aside id="menu" class="sidebar" aria-label="Navigation principale">
      <RouterLink to="/" class="brand">
        <img :src="logo" alt="IMM – Institut Montsouris" class="logo" />
        <span>Annuaire des gardes</span>
      </RouterLink>

      <nav class="nav">
        <p class="nav-title">Navigation</p>
        <RouterLink to="/" class="nav-item" exact-active-class="active">Annuaire du personnel</RouterLink>
        <RouterLink to="/garde" class="nav-item" active-class="active">Personnel de garde</RouterLink>

        <template v-if="auth.isAdmin">
          <p class="nav-title">Administration</p>
          <div v-for="g in navGroups" :key="g.id" class="nav-group">
            <button type="button" class="nav-group-btn" :aria-expanded="isOpen(g.id)" :aria-controls="`nav-${g.id}`" @click="toggleGroup(g.id)">
              {{ g.label }}
              <Icon :name="isOpen(g.id) ? 'chevron-up' : 'chevron-down'" />
            </button>
            <div v-show="isOpen(g.id)" :id="`nav-${g.id}`" class="nav-group-items">
              <RouterLink v-for="i in g.items" :key="i.id" :to="i.to" class="nav-item" active-class="active">{{ i.label }}</RouterLink>
            </div>
          </div>
        </template>
      </nav>

      <div class="side-foot">
        <p v-if="auth.isAdmin" class="side-user" :title="auth.username || 'Administrateur'">
          <Icon name="user" /><span>{{ auth.username || 'Administrateur' }}</span>
        </p>
        <button
          class="side-btn"
          :aria-label="theme === 'dark' ? 'Passer en mode clair' : 'Passer en mode sombre'"
          @click="toggleTheme"
        >
          <Icon :name="theme === 'dark' ? 'sun' : 'moon'" />
          {{ theme === 'dark' ? 'Mode clair' : 'Mode sombre' }}
        </button>
        <button v-if="auth.isAdmin" class="side-btn" @click="auth.logout()">Déconnexion</button>
        <RouterLink v-else to="/connexion" class="side-btn" active-class="active">Connexion</RouterLink>
      </div>
    </aside>

    <div class="scrim" @click="open = false"></div>

    <div class="toast-zone" role="status" aria-live="polite">
      <div v-if="toast.message" class="toast">
        <span>{{ toast.message }}</span>
        <button v-if="toast.action" type="button" class="toast-action" @click="runToastAction">{{ toast.action.label }}</button>
        <button type="button" class="toast-close" aria-label="Fermer" @click="hideToast"><Icon name="close" /></button>
      </div>
    </div>

    <div class="main">
      <header class="mobilebar">
        <button class="burger" aria-controls="menu" :aria-expanded="open" aria-label="Menu" @click="open = !open"><Icon name="menu" /></button>
        <img :src="logo" alt="IMM – Institut Montsouris" class="logo" />
      </header>
      <UrgenceBar />
      <main id="contenu" class="container" tabindex="-1">
        <RouterView />
      </main>
    </div>
  </div>
</template>
