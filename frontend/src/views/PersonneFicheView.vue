<script setup>
import { computed, ref, watchEffect } from 'vue'
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
const TEAM_PREVIEW = 8
const showTeam = ref(false)
const visibleTeam = computed(() => (showTeam.value ? p.value.equipe : p.value.equipe.slice(0, TEAM_PREVIEW)))

const fullName = (x) => [x.prenom, x.nom].filter(Boolean).join(' ') || x.username
const initials = (x) => (fullName(x).split(/[\s-]+/).filter(Boolean).slice(0, 2).map((w) => w[0].toUpperCase()).join(''))

// Retour à la liste telle qu'on l'a quittée (recherche et filtres conservés) ; lien direct : annuaire vide.
const back = () => (window.history.state?.back ? router.back() : router.push('/'))

watchEffect(async () => {
  p.value = null
  error.value = ''
  noPhoto.value = false
  showTeam.value = false
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
  <div v-else-if="p" class="pfiche">
    <aside class="pcard pfiche-id">
      <img
        v-if="!noPhoto"
        :src="apiUrl(`/personnes/${encodeURIComponent(p.username)}/photo`)"
        :alt="`Photo de ${fullName(p)}`"
        class="avatar-photo avatar-xl"
        @error="noPhoto = true"
      />
      <span v-else class="avatar avatar-xl" aria-hidden="true">{{ initials(p) }}</span>
      <h1>{{ fullName(p) }}</h1>
      <div v-if="p.service || p.metier" class="tags">
        <span v-if="p.service" class="tag tag-service">{{ p.service.libelle }}</span>
        <span v-if="p.metier" class="tag tag-metier">{{ p.metier.libelle }}</span>
      </div>
      <a
        v-if="auth.isAdmin && adEditUrl(p.username)"
        :href="adEditUrl(p.username)"
        target="_blank"
        rel="noopener"
        class="edit-btn edit-btn-lg"
        :aria-label="`Modifier la fiche de ${fullName(p)} dans l'AD`"
        title="Modifier dans l'AD"
      ><Icon name="edit" /> Modifier</a>
    </aside>

    <div class="pfiche-main">
      <section class="pcard">
        <h2><Icon name="phone" /> Contact</h2>
        <p v-if="!p.numeros.length && !p.email" class="muted">Aucun contact renseigné.</p>
        <div class="call-list">
          <CallNumber v-for="n in p.numeros" :key="n.numero" :numero="n" large />
          <a v-if="p.email" :href="`mailto:${p.email}`" class="mail-btn" :aria-label="`Écrire à ${fullName(p)}`">
            <Icon name="mail" /> {{ p.email }}
          </a>
        </div>
      </section>

      <section v-if="p.service || p.metier || p.matricule" class="pcard">
        <h2><Icon name="info" /> Informations</h2>
        <dl class="pinfo">
          <template v-if="p.service"><dt>Service</dt><dd>{{ p.service.libelle }}</dd></template>
          <template v-if="p.metier"><dt>Poste</dt><dd>{{ p.metier.libelle }}</dd></template>
          <template v-if="p.matricule"><dt>Matricule</dt><dd>{{ p.matricule }}</dd></template>
        </dl>
      </section>

      <section v-if="p.responsable" class="pcard">
        <h2><Icon name="user" /> Responsable</h2>
        <RouterLink :to="{ name: 'personne', params: { username: p.responsable.username } }" class="pmember">
          <span class="avatar" aria-hidden="true">{{ initials({ prenom: p.responsable.nom }) }}</span>
          <span>{{ p.responsable.nom }}</span>
        </RouterLink>
      </section>

      <section v-if="p.equipe?.length" class="pcard">
        <h2><Icon name="users" /> Équipe <span class="count">{{ p.equipe.length }}</span></h2>
        <div class="pteam">
          <RouterLink v-for="m in visibleTeam" :key="m.username" :to="{ name: 'personne', params: { username: m.username } }" class="pmember">
            <span class="avatar" aria-hidden="true">{{ initials({ prenom: m.nom }) }}</span>
            <span>{{ m.nom }}</span>
          </RouterLink>
        </div>
        <button v-if="p.equipe.length > TEAM_PREVIEW" type="button" class="pmore" @click="showTeam = !showTeam">
          {{ showTeam ? 'Réduire' : `Voir les ${p.equipe.length} personnes` }}
        </button>
      </section>
    </div>
  </div>
  <article v-else class="fiche skeleton" aria-busy="true" aria-label="Chargement">
    <span class="sk-avatar"></span>
    <span class="sk-line w60"></span>
    <span class="sk-line w40"></span>
  </article>
</template>
