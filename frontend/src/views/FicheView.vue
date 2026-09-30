<script setup>
import { ref, watchEffect } from 'vue'
import { get } from '../api'
import CallNumber from '../components/CallNumber.vue'
import Icon from '../components/Icon.vue'
import { pageTitle } from '../router'
import { useAuth } from '../stores/auth'

const props = defineProps({ id: String })
const auth = useAuth()
const p = ref(null)
const error = ref('')

const initials = (s) => s.split(/[\s-]+/).filter(Boolean).slice(0, 2).map((w) => w[0].toUpperCase()).join('')

watchEffect(async () => {
  p.value = null
  error.value = ''
  try {
    p.value = (await get(`/personnel/${props.id}`)).data
    document.title = pageTitle(p.value.libelle)
  } catch (e) {
    error.value = e.status === 404 ? 'Fiche introuvable.' : e.message
  }
})
</script>

<template>
  <RouterLink to="/garde" class="back"><Icon name="arrow-left" /> Retour à la liste</RouterLink>
  <p v-if="error" class="error">{{ error }}</p>
  <article v-else-if="p" class="fiche">
    <header class="fiche-head">
      <span class="avatar avatar-lg" aria-hidden="true">{{ initials(p.libelle) }}</span>
      <div>
        <h1>{{ p.libelle }}</h1>
        <div class="tags">
          <span class="tag tag-service">{{ p.service.libelle }}</span>
          <span class="tag tag-metier">{{ p.metier.libelle }}</span>
        </div>
      </div>
    </header>
    <dl>
      <dt>Service</dt>
      <dd>{{ p.service.libelle }}</dd>
      <dt>Localisation</dt>
      <dd>{{ p.service.localisation }}</dd>
      <dt>Métier</dt>
      <dd>{{ p.metier.libelle }}</dd>
    </dl>
    <h2>Numéros de garde</h2>
    <p v-if="!p.numerosGarde.length" class="muted">Aucun numéro.</p>
    <div class="call-list">
      <CallNumber v-for="n in p.numerosGarde" :key="n.id" :numero="n" large />
      <RouterLink
        v-if="auth.isAdmin"
        :to="{ name: 'personnel-edit', params: { id: p.id } }"
        class="edit-btn edit-btn-lg"
        :aria-label="`Modifier la fiche de ${p.libelle}`"
        title="Modifier la fiche"
      ><Icon name="edit" /> Modifier</RouterLink>
    </div>
  </article>
  <article v-else class="fiche skeleton" aria-busy="true" aria-label="Chargement">
    <span class="sk-avatar"></span>
    <span class="sk-line w60"></span>
    <span class="sk-line w40"></span>
  </article>
</template>
