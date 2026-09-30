<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue'
import { get } from '../api'
import Pagination from '../components/Pagination.vue'

const rows = ref([])
const page = ref(1)
const meta = reactive({ total: 0, totalPages: 1 })
const error = ref('')

// Filtres saisis, et filtres appliqués à la dernière recherche (le tableau ne change qu'au clic sur « Filtrer »)
const empty = () => ({ username: '', action: '', from: '', to: '' })
const filters = reactive(empty())
const applied = ref(empty())
const hasFilters = computed(() => Object.values(applied.value).some(Boolean))

async function load() {
  error.value = ''
  try {
    const res = await get('/traces', { page: page.value, ...applied.value })
    rows.value = res.data
    meta.total = res.total ?? res.data.length
    meta.totalPages = res.totalPages ?? 1
  } catch (e) {
    error.value = e.message
  }
}

function search() {
  applied.value = { ...filters, username: filters.username.trim() }
  if (page.value === 1) load()
  else page.value = 1
}

function reset() {
  Object.assign(filters, empty())
  search()
}

const fmt = (d) => new Date(d).toLocaleString('fr-FR')

watch(page, load)
onMounted(load)
</script>

<template>
  <h1>Journal des actions</h1>

  <form class="form trace-filters" @submit.prevent="search">
    <label>
      Utilisateur
      <input v-model="filters.username" maxlength="50" placeholder="Identifiant" />
    </label>
    <label>
      Action
      <select v-model="filters.action">
        <option value="">Toutes</option>
        <option value="Création">Création</option>
        <option value="Modification">Modification</option>
        <option value="Suppression">Suppression</option>
      </select>
    </label>
    <div class="trace-dates">
      <label>
        Du
        <input v-model="filters.from" type="date" :max="filters.to || undefined" />
      </label>
      <label>
        Au (inclus)
        <input v-model="filters.to" type="date" :min="filters.from || undefined" />
      </label>
    </div>
    <div class="actions">
      <button class="primary">Filtrer</button>
      <button v-if="hasFilters || Object.values(filters).some(Boolean)" type="button" @click="reset">Réinitialiser</button>
    </div>
  </form>

  <p v-if="error" class="error">{{ error }}</p>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Date</th>
          <th>Utilisateur</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="t in rows" :key="t.id">
          <td>{{ fmt(t.dateAction) }}</td>
          <td>{{ t.username }}</td>
          <td>{{ t.actionRealise }}</td>
        </tr>
      </tbody>
    </table>
    <div v-if="!rows.length && !error" class="empty empty-flat">
      <p><b>Aucune action</b></p>
      <p class="muted">{{ hasFilters ? 'Aucune action ne correspond à ces filtres.' : 'Le journal est vide pour le moment.' }}</p>
    </div>
  </div>
  <Pagination :page="page" :total-pages="meta.totalPages" :total="meta.total" @change="page = $event" />
</template>
