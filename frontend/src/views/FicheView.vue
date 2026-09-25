<script setup>
import { ref, watchEffect } from 'vue'
import { get } from '../api'

const props = defineProps({ id: String })
const p = ref(null)
const error = ref('')

watchEffect(async () => {
  p.value = null
  error.value = ''
  try {
    p.value = (await get(`/personnel/${props.id}`)).data
  } catch (e) {
    error.value = e.status === 404 ? 'Fiche introuvable.' : e.message
  }
})
</script>

<template>
  <RouterLink to="/" class="back"><span aria-hidden="true">←</span> Retour à la liste</RouterLink>
  <p v-if="error" class="error">{{ error }}</p>
  <article v-else-if="p" class="fiche">
    <h1>{{ p.libelle }}</h1>
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
    <ul class="numbers big">
      <li v-for="n in p.numerosGarde" :key="n.id">
        <a :href="`tel:${n.numero.replace(/\s/g, '')}`" class="chip">
          {{ n.type }} <b>{{ n.numero }}</b>
        </a>
      </li>
    </ul>
  </article>
  <p v-else class="muted">Chargement…</p>
</template>
