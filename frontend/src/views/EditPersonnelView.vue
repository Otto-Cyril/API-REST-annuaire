<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { get, post, put, del } from '../api'
import { pageTitle } from '../router'
import Icon from '../components/Icon.vue'
import AdPicker from '../components/AdPicker.vue'

const props = defineProps({ id: String })
const router = useRouter()

// Retour à la page d'où l'on vient (liste avec ses filtres, fiche, admin) ; à défaut, l'accueil.
// vue-router garde la page précédente dans history.state.back ; la page de connexion n'a pas d'intérêt comme retour.
const previous = window.history.state?.back
const hasBack = typeof previous === 'string' && previous.startsWith('/') && !previous.startsWith('/connexion')
const backHref = hasBack ? router.resolve(previous).href : '/garde'
const backLabel = !hasBack ? 'Retour à la liste' : previous.startsWith('/personnel/') ? 'Retour à la fiche' : previous.startsWith('/admin') ? "Retour à l'administration" : 'Retour à la liste'
const goBack = () => (hasBack ? router.back() : router.push('/garde'))

const p = ref(null)
const loadError = ref('')

const form = reactive({ username: '' })
const formError = ref('')
const fieldErrors = ref({})
const saving = ref(false)
const saved = ref(false)

// Numéros : chaque ligne est une copie éditable, enregistrée séparément.
const numbers = ref([])
const fresh = reactive({ type: '', numero: '' })
const freshError = ref('')
const freshBusy = ref(false)
const toDelete = ref(null)
const listError = ref('')

const toRow = (n) => ({ id: n.id, type: n.type, numero: n.numero, busy: false, error: '', ok: false })
const msg = (e) => (e.errors ? Object.values(e.errors).flat().join(' ') : '') || e.message

function hydrate() {
  Object.assign(form, { username: p.value.username })
  numbers.value = p.value.numerosGarde.map(toRow)
  document.title = pageTitle(`Modifier ${p.value.libelle}`)
}

async function load() {
  loadError.value = ''
  try {
    p.value = (await get(`/personnel/${props.id}`)).data
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
  try {
    // La réponse porte le libellé, le service et le métier relus dans l'AD si l'identifiant a changé.
    const res = await put(`/personnel/${props.id}`, { username: form.username })
    p.value = { ...p.value, ...res.data, numerosGarde: p.value.numerosGarde }
    Object.assign(form, { username: p.value.username })
    document.title = pageTitle(`Modifier ${p.value.libelle}`)
    saved.value = true
  } catch (e) {
    formError.value = e.message
    fieldErrors.value = e.errors ?? {}
  } finally {
    saving.value = false
  }
}

async function saveNumber(n) {
  n.busy = true
  n.error = ''
  n.ok = false
  try {
    await put(`/numeros-garde/${n.id}`, { numero: n.numero, type: n.type })
    n.ok = true
  } catch (e) {
    n.error = msg(e)
  } finally {
    n.busy = false
  }
}

async function addNumber() {
  freshBusy.value = true
  freshError.value = ''
  try {
    const { data } = await post('/numeros-garde', {
      numero: fresh.numero,
      type: fresh.type,
      personnelDeGardeId: Number(props.id),
    })
    numbers.value.push(toRow({ id: data.id, type: fresh.type, numero: fresh.numero }))
    fresh.type = ''
    fresh.numero = ''
  } catch (e) {
    freshError.value = msg(e)
  } finally {
    freshBusy.value = false
  }
}

async function removeNumber(n) {
  toDelete.value = null
  listError.value = ''
  try {
    await del(`/numeros-garde/${n.id}`)
    numbers.value = numbers.value.filter((x) => x.id !== n.id)
  } catch (e) {
    listError.value = msg(e)
  }
}

onMounted(load)
</script>

<template>
  <div class="edit-wrap">
  <a :href="backHref" class="back" @click.prevent="goBack"><Icon name="arrow-left" /> {{ backLabel }}</a>

  <p v-if="loadError" class="error">{{ loadError }}</p>

  <template v-else-if="p">
    <form class="edit-card" @submit.prevent="save">
      <header class="edit-head">
        <div>
          <h1>{{ p.libelle }}</h1>
          <p class="muted">Modifier la fiche du personnel de garde</p>
        </div>
      </header>

      <div class="edit-grid">
        <label class="wide">
          <span class="edit-label">Identifiant AD</span>
          <AdPicker v-model="form.username" :max="50" />
          <small class="muted">Cherchez la personne par son nom : le nom, le service et le métier sont lus automatiquement dans l'AD.</small>
          <small v-for="m in fieldErrors.username ?? []" :key="m" class="error">{{ m }}</small>
        </label>
        <p class="muted wide">
          <span v-if="p.service">{{ p.service.libelle }}</span><span v-else>Sans service</span>
          · <span v-if="p.metier">{{ p.metier.libelle }}</span><span v-else>sans poste</span>
          (lus dans l'AD, relus à chaque changement d'identifiant ou par <code>app:ldap:sync</code>)
        </p>
      </div>

      <p v-if="formError" class="error" role="alert">{{ formError }}</p>
      <footer class="edit-actions">
        <span v-if="saved" class="ok" role="status"><Icon name="check" /> Enregistré</span>
        <button class="primary" :disabled="saving">{{ saving ? 'Enregistrement…' : 'Enregistrer' }}</button>
      </footer>
    </form>

    <section class="edit-card" aria-labelledby="num-title">
      <header class="edit-head"><h2 id="num-title">Numéros de garde</h2></header>
      <p v-if="listError" class="error">{{ listError }}</p>
      <p v-if="!numbers.length" class="muted">Aucun numéro pour le moment.</p>

      <form v-for="n in numbers" :key="n.id" class="num-row" @submit.prevent="saveNumber(n)">
        <input v-model="n.type" list="types-numero" maxlength="50" required aria-label="Type" placeholder="Type" />
        <input v-model="n.numero" maxlength="50" required aria-label="Numéro" placeholder="Numéro" />
        <div class="actions">
          <button class="primary" :disabled="n.busy">Enregistrer</button>
          <button type="button" class="danger" @click="toDelete = n">Supprimer</button>
        </div>
        <span v-if="n.ok" class="ok" role="status"><Icon name="check" /> Enregistré</span>
        <small v-if="n.error" class="error num-msg">{{ n.error }}</small>
      </form>

      <form class="num-row num-new" @submit.prevent="addNumber">
        <input v-model="fresh.type" list="types-numero" maxlength="50" required aria-label="Type du nouveau numéro" placeholder="Type (Fixe, DECT, Mobile…)" />
        <input v-model="fresh.numero" maxlength="50" required aria-label="Nouveau numéro" placeholder="Nouveau numéro" />
        <div class="actions"><button :disabled="freshBusy">Ajouter</button></div>
        <small v-if="freshError" class="error num-msg">{{ freshError }}</small>
      </form>
      <datalist id="types-numero">
        <option value="Fixe"></option>
        <option value="DECT"></option>
        <option value="Mobile"></option>
        <option value="Bip"></option>
      </datalist>
    </section>

    <div v-if="toDelete" class="modal-back" @click.self="toDelete = null" @keydown.esc="toDelete = null">
      <div class="modal" role="alertdialog" aria-modal="true" aria-labelledby="del-num-title">
        <h2 id="del-num-title">Supprimer ce numéro ?</h2>
        <p class="muted">{{ toDelete.type }} {{ toDelete.numero }} — cette action est définitive.</p>
        <div class="actions">
          <button class="primary danger-fill" autofocus @click="removeNumber(toDelete)">Supprimer</button>
          <button type="button" @click="toDelete = null">Annuler</button>
        </div>
      </div>
    </div>
  </template>

  <article v-else class="fiche skeleton" aria-busy="true" aria-label="Chargement">
    <span class="sk-avatar"></span>
    <span class="sk-line w60"></span>
    <span class="sk-line w40"></span>
  </article>
  </div>
</template>
