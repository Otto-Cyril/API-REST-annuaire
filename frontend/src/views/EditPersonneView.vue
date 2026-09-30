<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { get, put } from '../api'
import { resources } from '../resources'
import { pageTitle } from '../router'
import Icon from '../components/Icon.vue'

const props = defineProps({ id: String })
const router = useRouter()
const cfg = resources.personnes

// Retour à l'annuaire d'où l'on vient (avec ses filtres) ; à défaut, l'accueil de l'annuaire.
const previous = window.history.state?.back
const hasBack = typeof previous === 'string' && previous.startsWith('/') && !previous.startsWith('/connexion')
const backHref = hasBack ? router.resolve(previous).href : '/'
const goBack = () => (hasBack ? router.back() : router.push('/'))

const p = ref(null)
const options = reactive({}) // { services: [...], metiers: [...] } pour les <select>
const loadError = ref('')

const form = reactive({})
const formError = ref('')
const fieldErrors = ref({})
const saving = ref(false)
const saved = ref(false)

const fullName = (r) => `${r.prenom} ${r.nom}`
const initials = computed(() => `${p.value?.prenom?.[0] ?? ''}${p.value?.nom?.[0] ?? ''}`.toUpperCase())

function hydrate() {
  const initial = cfg.toForm(p.value)
  for (const f of cfg.fields) form[f.key] = initial[f.key] ?? ''
  document.title = pageTitle(`Modifier ${fullName(p.value)}`)
}

async function load() {
  loadError.value = ''
  try {
    const selects = cfg.fields.filter((f) => f.options)
    const [pers, ...lists] = await Promise.all([
      get(`${cfg.path}/${props.id}`),
      ...selects.map((f) => get(resources[f.options].path, f.params)),
    ])
    selects.forEach((f, i) => (options[f.options] = lists[i].data))
    p.value = pers.data
    hydrate()
  } catch (e) {
    loadError.value = e.status === 404 ? 'Fiche introuvable.' : e.message
  }
}

async function save() {
  saving.value = true
  formError.value = ''
  fieldErrors.value = {}
  saved.value = false
  const body = { ...form }
  for (const f of cfg.fields) {
    if (f.options) body[f.key] = Number(body[f.key])
    else if (f.optional && body[f.key] === '') body[f.key] = null
  }
  try {
    await put(`${cfg.path}/${props.id}`, body)
    p.value = {
      ...p.value,
      nom: form.nom,
      prenom: form.prenom,
      service: options.services.find((s) => s.id === body.serviceId),
      metier: options.metiers.find((m) => m.id === body.metierId),
    }
    document.title = pageTitle(`Modifier ${fullName(p.value)}`)
    saved.value = true
  } catch (e) {
    formError.value = e.message
    fieldErrors.value = e.errors ?? {}
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="edit-wrap">
    <a :href="backHref" class="back" @click.prevent="goBack"><Icon name="arrow-left" /> Retour à l'annuaire</a>

    <p v-if="loadError" class="error">{{ loadError }}</p>

    <form v-else-if="p" class="edit-card" @submit.prevent="save">
      <header class="edit-head">
        <span class="edit-avatar" aria-hidden="true">{{ initials }}</span>
        <div>
          <h1>{{ fullName(p) }}</h1>
          <p class="muted">{{ p.service?.libelle }} · {{ p.metier?.libelle }}</p>
        </div>
      </header>

      <div class="edit-grid">
        <label v-for="f in cfg.fields" :key="f.key" :class="{ wide: f.wide }">
          <span class="edit-label">{{ f.label }}<em v-if="f.optional"> (facultatif)</em></span>
          <select v-if="f.options" v-model="form[f.key]" required>
            <option value="" disabled>Choisir…</option>
            <option v-for="o in options[f.options] ?? []" :key="o.id" :value="o.id">{{ o[f.optionLabel] }}</option>
          </select>
          <input v-else v-model="form[f.key]" :type="f.type ?? 'text'" :maxlength="f.max" :required="!f.optional" />
          <small v-for="m in fieldErrors[f.key] ?? []" :key="m" class="error" role="alert">{{ m }}</small>
        </label>
      </div>

      <p v-if="formError" class="error" role="alert">{{ formError }}</p>
      <footer class="edit-actions">
        <span v-if="saved" class="ok" role="status"><Icon name="check" /> Enregistré</span>
        <button type="button" @click="goBack">Retour</button>
        <button class="primary" :disabled="saving">{{ saving ? 'Enregistrement…' : 'Enregistrer' }}</button>
      </footer>
    </form>

    <article v-else class="fiche skeleton" aria-busy="true" aria-label="Chargement">
      <span class="sk-avatar"></span>
      <span class="sk-line w60"></span>
      <span class="sk-line w40"></span>
    </article>
  </div>
</template>
