<script setup>
import { ref, watchEffect } from 'vue'
import { useRouter } from 'vue-router'
import { apiUrl, get } from '../api'
import CallNumber from '../components/CallNumber.vue'
import Icon from '../components/Icon.vue'
import { pageTitle } from '../router'
import { useAuth } from '../stores/auth'
import { adEditUrl } from '../adLink'

const props = defineProps({ username: String })
const router = useRouter()
const auth = useAuth()
const p = ref(null)
const error = ref('')
const noPhoto = ref(false) // pas de photo dans l'AD : on affiche les initiales

const fullName = (x) => [x.prenom, x.nom].filter(Boolean).join(' ') || x.username
const initials = (x) => (fullName(x).split(/[\s-]+/).filter(Boolean).slice(0, 2).map((w) => w[0].toUpperCase()).join(''))

// Retour à la liste telle qu'on l'a quittée (recherche et filtres conservés) ; lien direct : annuaire vide.
const back = () => (window.history.state?.back ? router.back() : router.push('/'))

watchEffect(async () => {
  p.value = null
  error.value = ''
  noPhoto.value = false
  try {
    p.value = (await get(`/personnes/${encodeURIComponent(props.username)}`)).data
    document.title = pageTitle(fullName(p.value))
  } catch (e) {
    error.value = e.status === 404 ? 'Fiche introuvable.' : e.message
  }
})
</script>

<template>
  <a href="/" class="back" @click.prevent="back"><Icon name="arrow-left" /> Retour à la liste</a>
  <p v-if="error" class="error">{{ error }}</p>
  <article v-else-if="p" class="fiche">
    <header class="fiche-head">
      <img
        v-if="!noPhoto"
        :src="apiUrl(`/personnes/${encodeURIComponent(p.username)}/photo`)"
        :alt="`Photo de ${fullName(p)}`"
        class="avatar-photo"
        @error="noPhoto = true"
      />
      <span v-else class="avatar avatar-lg" aria-hidden="true">{{ initials(p) }}</span>
      <div>
        <h1>{{ fullName(p) }}</h1>
        <p v-if="p.matricule" class="muted fiche-loc">Matricule : {{ p.matricule }}</p>
        <div v-if="p.service || p.metier" class="tags">
          <span v-if="p.service" class="tag tag-service">{{ p.service.libelle }}</span>
          <span v-if="p.metier" class="tag tag-metier">{{ p.metier.libelle }}</span>
        </div>
      </div>
      <a
        v-if="auth.isAdmin && adEditUrl(p.username)"
        :href="adEditUrl(p.username)"
        target="_blank"
        rel="noopener"
        class="edit-btn edit-btn-lg fiche-edit"
        :aria-label="`Modifier la fiche de ${fullName(p)} dans l'AD`"
        title="Modifier dans l'AD"
      ><Icon name="edit" /> Modifier</a>
    </header>
    <h2 class="fiche-section">Contact</h2>
    <p v-if="!p.numeros.length && !p.email" class="muted">Aucun contact renseigné.</p>
    <div class="call-list">
      <CallNumber v-for="n in p.numeros" :key="n.numero" :numero="n" large />
      <a v-if="p.email" :href="`mailto:${p.email}`" class="mail-btn" :aria-label="`Écrire à ${fullName(p)}`">
        <Icon name="mail" /> {{ p.email }}
      </a>
    </div>
    <template v-if="p.responsable">
      <h2 class="fiche-section">Responsable</h2>
      <div class="call-list">
        <RouterLink :to="{ name: 'personne', params: { username: p.responsable.username } }" class="mail-btn">{{ p.responsable.nom }}</RouterLink>
      </div>
    </template>
    <template v-if="p.equipe?.length">
      <h2 class="fiche-section">Équipe ({{ p.equipe.length }})</h2>
      <div class="call-list">
        <RouterLink v-for="m in p.equipe" :key="m.username" :to="{ name: 'personne', params: { username: m.username } }" class="mail-btn">{{ m.nom }}</RouterLink>
      </div>
    </template>
  </article>
  <article v-else class="fiche skeleton" aria-busy="true" aria-label="Chargement">
    <span class="sk-avatar"></span>
    <span class="sk-line w60"></span>
    <span class="sk-line w40"></span>
  </article>
</template>
