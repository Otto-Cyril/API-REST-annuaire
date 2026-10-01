<script setup>
// Champ « Identifiant AD » avec recherche : on tape un nom (2 caractères au moins) et on choisit dans les comptes de l'AD.
// On peut aussi saisir directement l'identifiant exact : la valeur du champ reste toujours l'identifiant (v-model).
import { ref, onBeforeUnmount } from 'vue'
import { get } from '../api'

const props = defineProps({ modelValue: { type: String, default: '' }, max: { type: Number, default: 50 } })
const emit = defineEmits(['update:modelValue'])

const results = ref([])
const open = ref(false)
const loading = ref(false)
const error = ref('')
const chosen = ref(null)
const active = ref(-1)

let timer
let seq = 0

function onInput(e) {
  const value = e.target.value
  emit('update:modelValue', value)
  chosen.value = null
  active.value = -1
  clearTimeout(timer)
  if (value.trim().length < 2) {
    seq++ // ignore une réponse encore en vol
    results.value = []
    error.value = ''
    open.value = false
    return
  }
  timer = setTimeout(() => search(value.trim()), 300)
}

async function search(q) {
  const mine = ++seq
  loading.value = true
  error.value = ''
  try {
    const res = await get('/ad/recherche', { q })
    if (mine !== seq) return
    results.value = res.data
  } catch (e) {
    if (mine !== seq) return
    results.value = []
    error.value = e.message
  } finally {
    if (mine === seq) {
      loading.value = false
      open.value = true
    }
  }
}

function choose(account) {
  emit('update:modelValue', account.username)
  chosen.value = account
  open.value = false
  results.value = []
  active.value = -1
}

function onKeydown(e) {
  if (e.key === 'Escape') {
    open.value = false
  } else if (open.value && results.value.length && (e.key === 'ArrowDown' || e.key === 'ArrowUp')) {
    e.preventDefault()
    const n = results.value.length
    active.value = (active.value + (e.key === 'ArrowDown' ? 1 : -1) + n) % n
  } else if (e.key === 'Enter' && open.value && active.value >= 0) {
    e.preventDefault() // choisir un résultat, pas envoyer le formulaire
    choose(results.value[active.value])
  }
}

onBeforeUnmount(() => clearTimeout(timer))
</script>

<template>
  <div class="ad-picker">
    <input
      :value="props.modelValue"
      type="text"
      autocomplete="off"
      role="combobox"
      aria-autocomplete="list"
      :aria-expanded="open"
      :maxlength="props.max"
      placeholder="Rechercher un nom ou saisir l'identifiant"
      required
      @input="onInput"
      @keydown="onKeydown"
      @blur="open = false"
    />
    <ul v-if="open" class="ad-results" role="listbox">
      <li v-if="error" class="ad-empty error" role="alert">{{ error }}</li>
      <li v-else-if="!results.length" class="ad-empty muted">Aucun compte trouvé.</li>
      <li v-for="(r, i) in results" :key="r.username" role="option" :aria-selected="i === active">
        <button type="button" :class="{ active: i === active }" @mousedown.prevent="choose(r)">
          <b>{{ r.libelle }}</b>
          <span class="muted">{{ r.username }}<template v-if="r.email"> · {{ r.email }}</template></span>
        </button>
      </li>
    </ul>
    <small v-if="chosen" class="muted">{{ chosen.libelle }}<template v-if="chosen.email"> · {{ chosen.email }}</template></small>
    <small v-else-if="loading" class="muted" aria-live="polite">Recherche…</small>
  </div>
</template>
