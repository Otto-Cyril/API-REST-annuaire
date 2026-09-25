<script setup>
import { ref, reactive, watch, onMounted } from 'vue'
import { get } from '../api'
import Pagination from '../components/Pagination.vue'

const rows = ref([])
const page = ref(1)
const meta = reactive({ total: 0, totalPages: 1 })
const error = ref('')

async function load() {
  error.value = ''
  try {
    const res = await get('/traces', { page: page.value })
    rows.value = res.data
    meta.total = res.total ?? res.data.length
    meta.totalPages = res.totalPages ?? 1
  } catch (e) {
    error.value = e.message
  }
}

const fmt = (d) => new Date(d).toLocaleString('fr-FR')

watch(page, load)
onMounted(load)
</script>

<template>
  <h1>Journal des actions</h1>
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
  </div>
  <Pagination :page="page" :total-pages="meta.totalPages" :total="meta.total" @change="page = $event" />
</template>
