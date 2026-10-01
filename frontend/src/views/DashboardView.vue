<script setup>
// Tableau de bord (administration) : effectifs de l'AD par service et par métier, en tableaux. Un clic sur une ligne ouvre l'annuaire filtré.
import { ref, onMounted } from 'vue'
import { get } from '../api'
import CountTable from '../components/CountTable.vue'

const stats = ref(null)
const error = ref('')

async function load() {
  error.value = ''
  try {
    stats.value = (await get('/personnes/stats')).data
  } catch (e) {
    error.value = e.message
  }
}

onMounted(load)
</script>

<template>
  <h1>Tableau de bord</h1>

  <div v-if="error" class="error-box" role="alert">
    <p class="error">{{ error }}</p>
    <button type="button" @click="load">Réessayer</button>
  </div>

  <p v-else-if="!stats" class="muted" aria-live="polite">Chargement…</p>

  <template v-else>
    <section class="stats" aria-label="Chiffres clés de l'AD">
      <RouterLink :to="{ name: 'annuaire' }" class="stat stat-link"><b>{{ stats.total }}</b><span>Personnel</span></RouterLink>
      <div class="stat"><b>{{ stats.services.length }}</b><span>Services</span></div>
      <div class="stat"><b>{{ stats.metiers.length }}</b><span>Métiers</span></div>
    </section>

    <div class="ct-grid">
      <CountTable title="Services" query-key="service" :items="stats.services" />
      <CountTable title="Métiers" query-key="metier" :items="stats.metiers" />
    </div>

    <p class="muted dash-note">Données lues dans l'AD (comptes actifs de l'annuaire du personnel) ; un clic sur une ligne ouvre l'annuaire filtré.</p>
  </template>
</template>
