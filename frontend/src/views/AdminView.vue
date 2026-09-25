<script setup>
import { ref, reactive, computed, watch } from 'vue'
import { get, post, put, del } from '../api'
import { resources } from '../resources'
import Pagination from '../components/Pagination.vue'

const props = defineProps({ resource: String })
const cfg = computed(() => resources[props.resource])

const rows = ref([])
const page = ref(1)
const meta = reactive({ total: 0, totalPages: 1 })
const options = reactive({}) // { services: [...], ... } pour les <select>
const error = ref('')
const editing = ref(null) // null = fermé, 0 = création, sinon id
const form = reactive({})
const formError = ref('')
const fieldErrors = ref({})
const saving = ref(false)

const cell = (row, col) => (col.get ? col.get(row) : row[col.key]) ?? ''

async function load() {
  if (!cfg.value) return
  error.value = ''
  try {
    const res = await get(cfg.value.path, cfg.value.paginated ? { page: page.value } : undefined)
    rows.value = res.data
    meta.total = res.total ?? res.data.length
    meta.totalPages = res.totalPages ?? 1
  } catch (e) {
    error.value = e.message
  }
}

async function loadOptions() {
  for (const f of cfg.value?.fields ?? []) {
    if (!f.options) continue
    try {
      options[f.options] = (await get(resources[f.options].path, f.params)).data
    } catch (e) {
      error.value = e.message
    }
  }
}

function open(row) {
  for (const k of Object.keys(form)) delete form[k]
  const initial = row ? (cfg.value.toForm ? cfg.value.toForm(row) : row) : {}
  for (const f of cfg.value.fields) form[f.key] = initial[f.key] ?? ''
  editing.value = row ? row.id : 0
  formError.value = ''
  fieldErrors.value = {}
}

async function save() {
  saving.value = true
  formError.value = ''
  fieldErrors.value = {}
  const body = { ...form }
  for (const f of cfg.value.fields) if (f.options) body[f.key] = Number(body[f.key]) // ids envoyés en entiers
  try {
    if (editing.value === 0) await post(cfg.value.path, body)
    else await put(`${cfg.value.path}/${editing.value}`, body)
    editing.value = null
    await load()
  } catch (e) {
    formError.value = e.message
    fieldErrors.value = e.errors ?? {}
  } finally {
    saving.value = false
  }
}

async function remove(row) {
  if (!confirm('Supprimer cet élément ?')) return
  error.value = ''
  try {
    await del(`${cfg.value.path}/${row.id}`)
    if (rows.value.length === 1 && page.value > 1) page.value--
    else await load()
  } catch (e) {
    error.value = e.message
  }
}

watch(
  () => props.resource,
  () => {
    editing.value = null
    rows.value = []
    page.value = 1
    load()
    loadOptions()
  },
  { immediate: true },
)
watch(page, load)
</script>

<template>
  <template v-if="cfg">
    <div class="head">
      <h1>{{ cfg.title }}</h1>
      <button class="primary" @click="open(null)">Ajouter</button>
    </div>

    <p v-if="error" class="error">{{ error }}</p>

    <form v-if="editing !== null" class="form" @submit.prevent="save">
      <h2>{{ editing === 0 ? 'Nouvel élément' : `Modifier #${editing}` }}</h2>
      <label v-for="f in cfg.fields" :key="f.key">
        {{ f.label }}
        <select v-if="f.options" v-model="form[f.key]" required>
          <option value="" disabled>Choisir…</option>
          <option v-for="o in options[f.options] ?? []" :key="o.id" :value="o.id">{{ o[f.optionLabel] }}</option>
        </select>
        <input v-else v-model="form[f.key]" :maxlength="f.max" required />
        <small v-for="m in fieldErrors[f.key] ?? []" :key="m" class="error">{{ m }}</small>
      </label>
      <p v-if="formError" class="error">{{ formError }}</p>
      <div class="actions">
        <button class="primary" :disabled="saving">Enregistrer</button>
        <button type="button" @click="editing = null">Annuler</button>
      </div>
    </form>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th v-for="c in cfg.columns" :key="c.key">{{ c.label }}</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="r in rows" :key="r.id">
            <td v-for="c in cfg.columns" :key="c.key">{{ cell(r, c) }}</td>
            <td class="actions">
              <button @click="open(r)">Modifier</button>
              <button class="danger" @click="remove(r)">Supprimer</button>
            </td>
          </tr>
          <tr v-if="!rows.length">
            <td :colspan="cfg.columns.length + 1" class="muted">Aucun élément.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <Pagination v-if="cfg.paginated" :page="page" :total-pages="meta.totalPages" :total="meta.total" @change="page = $event" />
  </template>
  <p v-else class="error">Ressource inconnue.</p>
</template>
