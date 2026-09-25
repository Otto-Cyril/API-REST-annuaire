<script setup>
import { ref, reactive, watch, onMounted } from 'vue'
import { get } from '../api'
import Pagination from '../components/Pagination.vue'

const filters = reactive({ q: '', serviceId: '', metierId: '' })
const page = ref(1)
const list = ref([])
const meta = reactive({ total: 0, totalPages: 1 })
const services = ref([])
const metiers = ref([])
const loading = ref(false)
const error = ref('')

let timer
let seq = 0

async function load() {
  const mine = ++seq
  loading.value = true
  error.value = ''
  try {
    const res = await get('/personnel', { ...filters, page: page.value })
    if (mine !== seq) return // une requête plus récente a pris le relais
    list.value = res.data
    meta.total = res.total ?? res.data.length
    meta.totalPages = res.totalPages ?? 1
  } catch (e) {
    if (mine === seq) error.value = e.message
  } finally {
    if (mine === seq) loading.value = false
  }
}

// Toute modification de filtre revient à la page 1 ; la saisie est temporisée.
watch(filters, () => {
  clearTimeout(timer)
  timer = setTimeout(() => {
    page.value === 1 ? load() : (page.value = 1)
  }, 300)
})
watch(page, load)

onMounted(async () => {
  load()
  try {
    services.value = (await get('/services')).data
    metiers.value = (await get('/metiers')).data
  } catch {
    /* les filtres restent vides, la recherche texte fonctionne toujours */
  }
})
</script>

<template>
  <h1>Personnel de garde</h1>

  <section class="stats" aria-label="Chiffres clés">
    <div class="stat"><b>{{ meta.total }}</b><span>Personnel</span></div>
    <div class="stat"><b>{{ services.length }}</b><span>Services</span></div>
    <div class="stat"><b>{{ metiers.length }}</b><span>Métiers</span></div>
  </section>

  <form class="filters" @submit.prevent="load">
    <input v-model="filters.q" type="search" maxlength="50" placeholder="Rechercher (nom, service, métier…)" />
    <select v-model="filters.serviceId" aria-label="Service">
      <option value="">Tous les services</option>
      <option v-for="s in services" :key="s.id" :value="s.id">{{ s.libelle }}</option>
    </select>
    <select v-model="filters.metierId" aria-label="Métier">
      <option value="">Tous les métiers</option>
      <option v-for="m in metiers" :key="m.id" :value="m.id">{{ m.libelle }}</option>
    </select>
  </form>

  <p v-if="error" class="error">{{ error }}</p>
  <p v-else-if="loading && !list.length" class="muted">Chargement…</p>
  <p v-else-if="!list.length" class="muted">Aucun résultat.</p>

  <ul class="cards" :class="{ busy: loading }">
    <li v-for="p in list" :key="p.id" class="card">
      <RouterLink :to="{ name: 'fiche', params: { id: p.id } }" class="card-title">{{ p.libelle }}</RouterLink>
      <div class="muted">{{ p.service.libelle }} · {{ p.service.localisation }} · {{ p.metier.libelle }}</div>
      <div class="numbers">
        <a v-for="n in p.numerosGarde" :key="n.id" :href="`tel:${n.numero.replace(/\s/g, '')}`" class="chip">
          {{ n.type }} <b>{{ n.numero }}</b>
        </a>
      </div>
    </li>
  </ul>

  <Pagination :page="page" :total-pages="meta.totalPages" :total="meta.total" @change="page = $event" />
</template>
