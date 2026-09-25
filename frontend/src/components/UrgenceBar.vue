<script setup>
import { ref, reactive, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import { get, post, put, del } from '../api'
import { useAuth } from '../stores/auth'

const auth = useAuth()

const REFRESH_MS = 60_000
const numeros = ref([])
const garde = ref([])
const total = ref(0)
const failed = ref(false)
let timer

const panel = ref(null) // null = replié, 'urgences' ou 'garde'
const vital = computed(() => numeros.value[0])

// Gestion des numéros d'urgence (admin connecté)
const managing = ref(false)
const editing = ref(null) // null = formulaire fermé, 0 = création, sinon id
const form = reactive({ libelle: '', numero: '' })
const formError = ref('')
const saving = ref(false)

function toggle(name) {
  panel.value = panel.value === name ? null : name
  stopManaging()
}

function stopManaging() {
  managing.value = false
  editing.value = null
  formError.value = ''
  gManaging.value = false
  gForm.value = null
  gError.value = ''
}

// Gestion de la garde en cours (admin connecté) : personnes et numéros de garde
const gManaging = ref(false)
const gForm = ref(null) // null = fermé, sinon { kind: 'personne' | 'numero', id, ... }
const gError = ref('')
const gSaving = ref(false)
const services = ref([])
const metiers = ref([])

async function toggleGManaging() {
  gManaging.value = !gManaging.value
  gForm.value = null
  gError.value = ''
  if (gManaging.value && !services.value.length) {
    try {
      services.value = (await get('/services')).data
      metiers.value = (await get('/metiers')).data
    } catch (e) {
      gError.value = describe(e)
    }
  }
}

function gEditPerson(p) {
  gForm.value = {
    kind: 'personne',
    id: p ? p.id : 0,
    libelle: p?.libelle ?? '',
    serviceId: p?.service?.id ?? '',
    metierId: p?.metier?.id ?? '',
  }
  gError.value = ''
}

function gEditNumero(p, n) {
  gForm.value = { kind: 'numero', id: n ? n.id : 0, personId: p.id, personLabel: p.libelle, numero: n?.numero ?? '', type: n?.type ?? '' }
  gError.value = ''
}

async function gSave() {
  const f = gForm.value
  gSaving.value = true
  gError.value = ''
  try {
    if (f.kind === 'personne') {
      const body = { libelle: f.libelle.trim(), serviceId: Number(f.serviceId), metierId: Number(f.metierId) }
      if (f.id === 0) await post('/personnel', body)
      else await put(`/personnel/${f.id}`, body)
    } else {
      const body = { numero: f.numero.trim(), type: f.type.trim(), personnelDeGardeId: f.personId }
      if (f.id === 0) await post('/numeros-garde', body)
      else await put(`/numeros-garde/${f.id}`, body)
    }
    gForm.value = null
    await load()
  } catch (e) {
    gError.value = describe(e)
  } finally {
    gSaving.value = false
  }
}

async function gRemovePerson(p) {
  if (!confirm(`Supprimer « ${p.libelle} » de la garde ?`)) return
  gError.value = ''
  try {
    await del(`/personnel/${p.id}`)
    gForm.value = null
    await load()
  } catch (e) {
    gError.value = describe(e)
  }
}

async function gRemoveNumero(n) {
  if (!confirm(`Supprimer le numéro « ${n.type} ${n.numero} » ?`)) return
  gError.value = ''
  try {
    await del(`/numeros-garde/${n.id}`)
    gForm.value = null
    await load()
  } catch (e) {
    gError.value = describe(e)
  }
}

function toggleManaging() {
  managing.value = !managing.value
  editing.value = null
  formError.value = ''
}

function edit(n) {
  form.libelle = n?.libelle ?? ''
  form.numero = n?.numero ?? ''
  editing.value = n ? n.id : 0
  formError.value = ''
}

function cancel() {
  editing.value = null
  formError.value = ''
}

function describe(e) {
  const details = e.errors && typeof e.errors === 'object' ? Object.values(e.errors).join(' ') : ''
  return details ? `${e.message} ${details}` : e.message
}

async function save() {
  saving.value = true
  formError.value = ''
  const body = { libelle: form.libelle.trim(), numero: form.numero.trim() }
  try {
    if (editing.value === 0) await post('/numeros-urgence', body)
    else await put(`/numeros-urgence/${editing.value}`, body)
    editing.value = null
    await load()
  } catch (e) {
    formError.value = describe(e)
  } finally {
    saving.value = false
  }
}

async function remove(n) {
  if (!confirm(`Supprimer « ${n.libelle} » (${n.numero}) ?`)) return
  formError.value = ''
  try {
    await del(`/numeros-urgence/${n.id}`)
    if (editing.value === n.id) editing.value = null
    await load()
  } catch (e) {
    formError.value = describe(e)
  }
}

// Déconnexion : on quitte le mode gestion.
watch(() => auth.isAdmin, (admin) => {
  if (!admin) stopManaging()
})

const initials = (s) => s.split(/[\s-]+/).filter(Boolean).slice(0, 2).map((w) => w[0].toUpperCase()).join('')

const tel = (n) => `tel:${n.replace(/\s/g, '')}`

async function load() {
  try {
    const [urgences, personnel] = await Promise.all([get('/numeros-urgence'), get('/personnel', { limit: 100 })])
    numeros.value = urgences.data
    garde.value = personnel.data
    total.value = personnel.total ?? personnel.data.length
    failed.value = false
  } catch {
    failed.value = true
  }
}

onMounted(() => {
  load()
  timer = setInterval(load, REFRESH_MS)
})
onBeforeUnmount(() => clearInterval(timer))
</script>

<template>
  <div class="urgence-wrap">
    <section class="urgence" aria-label="Urgences et garde en cours">
      <div class="urgence-bar">
        <span class="pulse" aria-hidden="true"></span>
        <a v-if="vital" :href="tel(vital.numero)" class="vital">
          <span class="vital-label">{{ vital.libelle }}</span>
          <b class="vital-num">{{ vital.numero }}</b>
        </a>
        <span v-else-if="failed" class="muted">Urgences indisponibles</span>
        <span class="bar-spacer"></span>
        <button class="bar-btn" :aria-expanded="panel === 'urgences'" @click="toggle('urgences')">
          <span class="ico ico-red" aria-hidden="true">✚</span> Numéros d'urgence <span class="count">{{ numeros.length }}</span>
          <span aria-hidden="true">{{ panel === 'urgences' ? '▴' : '▾' }}</span>
        </button>
        <button class="bar-btn" :aria-expanded="panel === 'garde'" @click="toggle('garde')">
          <span class="ico ico-blue" aria-hidden="true">●</span> Garde en cours <span class="count">{{ total }}</span>
          <span aria-hidden="true">{{ panel === 'garde' ? '▴' : '▾' }}</span>
        </button>
      </div>

      <div v-if="panel === 'urgences'" class="urgence-panel">
        <div class="panel-head">
          <h2>Numéros d'urgence</h2>
          <button v-if="auth.isAdmin" class="manage-btn" :aria-pressed="managing" @click="toggleManaging">
            {{ managing ? 'Terminer' : '⚙ Gérer' }}
          </button>
        </div>
        <p v-if="failed" class="muted">Indisponibles pour le moment.</p>
        <ul v-else class="urgence-list">
          <li v-for="(n, i) in numeros" :key="n.id" :class="{ managed: managing }" :style="{ '--i': Math.min(i, 12) }">
            <a :href="tel(n.numero)" class="urgence-num">
              <span>{{ n.libelle }}</span>
              <b>{{ n.numero }}</b>
            </a>
            <span v-if="managing" class="row-actions">
              <button :aria-label="`Modifier ${n.libelle}`" title="Modifier" @click="edit(n)">✎</button>
              <button class="danger" :aria-label="`Supprimer ${n.libelle}`" title="Supprimer" @click="remove(n)">✕</button>
            </span>
          </li>
        </ul>

        <template v-if="managing">
          <form v-if="editing !== null" class="urgence-form" @submit.prevent="save">
            <strong>{{ editing === 0 ? 'Nouveau numéro' : 'Modifier le numéro' }}</strong>
            <input v-model="form.libelle" maxlength="50" placeholder="Libellé" aria-label="Libellé" required />
            <input v-model="form.numero" maxlength="50" placeholder="Numéro" aria-label="Numéro" required inputmode="tel" />
            <div class="actions">
              <button class="primary" :disabled="saving">{{ saving ? '…' : 'Enregistrer' }}</button>
              <button type="button" @click="cancel">Annuler</button>
            </div>
          </form>
          <button v-else class="add-btn" @click="edit(null)">+ Ajouter un numéro</button>
          <p v-if="formError" class="error">{{ formError }}</p>
        </template>
      </div>

      <div v-if="panel === 'garde'" class="urgence-panel">
        <div class="panel-head">
          <h2>Garde en cours</h2>
          <button v-if="auth.isAdmin" class="manage-btn" :aria-pressed="gManaging" @click="toggleGManaging">
            {{ gManaging ? 'Terminer' : '⚙ Gérer' }}
          </button>
        </div>
        <p v-if="failed" class="muted">Indisponible pour le moment.</p>
        <p v-else-if="!garde.length" class="muted">Aucun personnel de garde enregistré.</p>
        <ul v-else class="garde-list">
          <li v-for="(p, i) in garde" :key="p.id" class="garde-item" :style="{ '--i': Math.min(i, 12) }">
            <span class="avatar" aria-hidden="true">{{ initials(p.libelle) }}</span>
            <RouterLink :to="{ name: 'fiche', params: { id: p.id } }" class="garde-name">{{ p.libelle }}</RouterLink>
            <span class="muted">{{ p.service.libelle }} · {{ p.metier.libelle }}</span>
            <span class="garde-nums">
              <span v-for="n in p.numerosGarde" :key="n.id" class="chip-wrap">
                <a :href="tel(n.numero)" class="chip">{{ n.type }} <b>{{ n.numero }}</b></a>
                <template v-if="gManaging">
                  <button class="mini" :aria-label="`Modifier ${n.type} ${n.numero}`" title="Modifier" @click="gEditNumero(p, n)">✎</button>
                  <button class="mini danger" :aria-label="`Supprimer ${n.type} ${n.numero}`" title="Supprimer" @click="gRemoveNumero(n)">✕</button>
                </template>
              </span>
              <button v-if="gManaging" class="mini add" @click="gEditNumero(p, null)">+ numéro</button>
            </span>
            <span v-if="gManaging" class="person-actions">
              <button class="mini" @click="gEditPerson(p)">✎ Modifier</button>
              <button class="mini danger" @click="gRemovePerson(p)">✕ Supprimer</button>
            </span>
          </li>
        </ul>

        <template v-if="gManaging">
          <form v-if="gForm" class="urgence-form" @submit.prevent="gSave">
            <template v-if="gForm.kind === 'personne'">
              <strong>{{ gForm.id === 0 ? 'Nouvelle personne de garde' : 'Modifier la personne' }}</strong>
              <input v-model="gForm.libelle" maxlength="50" placeholder="Nom / libellé" aria-label="Nom" required />
              <select v-model="gForm.serviceId" aria-label="Service" required>
                <option value="" disabled>Service…</option>
                <option v-for="s in services" :key="s.id" :value="s.id">{{ s.libelle }}</option>
              </select>
              <select v-model="gForm.metierId" aria-label="Métier" required>
                <option value="" disabled>Métier…</option>
                <option v-for="m in metiers" :key="m.id" :value="m.id">{{ m.libelle }}</option>
              </select>
            </template>
            <template v-else>
              <strong>{{ gForm.id === 0 ? 'Nouveau numéro' : 'Modifier le numéro' }} · {{ gForm.personLabel }}</strong>
              <input v-model="gForm.type" maxlength="50" placeholder="Type (ex. Mobile, DECT…)" aria-label="Type" required />
              <input v-model="gForm.numero" maxlength="50" placeholder="Numéro" aria-label="Numéro" required inputmode="tel" />
            </template>
            <div class="actions">
              <button class="primary" :disabled="gSaving">{{ gSaving ? '…' : 'Enregistrer' }}</button>
              <button type="button" @click="gForm = null">Annuler</button>
            </div>
          </form>
          <button v-else class="add-btn" @click="gEditPerson(null)">+ Ajouter une personne de garde</button>
          <p v-if="gError" class="error">{{ gError }}</p>
        </template>
      </div>
    </section>
  </div>
</template>
