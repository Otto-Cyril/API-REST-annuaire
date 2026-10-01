<script setup>
import { ref, reactive, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import { useRouter } from 'vue-router'
import { get, post, put, del } from '../api'
import { useAuth } from '../stores/auth'
import Icon from './Icon.vue'
import { frDate, today, relativeDay, peopleOnDuty, groupByService } from '../garde'
import AdPicker from './AdPicker.vue'
import { iconOf, URGENCE_ICONS } from '../urgence'

const auth = useAuth()
const router = useRouter()

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
const form = reactive({ libelle: '', numero: '', icone: '' })
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

function toggleGManaging() {
  gManaging.value = !gManaging.value
  gForm.value = null
  gError.value = ''
}

function gEditPerson(p) {
  gForm.value = {
    kind: 'personne',
    id: p.id,
    username: p.username ?? '',
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
      await put(`/personnel/${f.id}`, { username: f.username.trim() })
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
  const periodes = p.periodes.map((g) => `du ${frDate(g.dateDebut)} au ${frDate(g.dateFin)}`).join(', ')
  if (!confirm(`Retirer « ${p.libelle} » de la garde (${periodes}) ?`)) return
  gError.value = ''
  try {
    for (const g of p.periodes) await del(`/gardes/${g.id}`)
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
  form.icone = n?.icone ?? ''
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
  const body = { libelle: form.libelle.trim(), numero: form.numero.trim(), icone: form.icone || null }
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

const tel = (n) => `tel:${n.replace(/\s/g, '')}`

const initials = (s) => s.split(/[\s-]+/).filter(Boolean).slice(0, 2).map((w) => w[0].toUpperCase()).join('')

// Prochaine garde à venir (affichée seulement quand personne n'est de garde aujourd'hui) et filtre par service
const prochaines = ref([])
const prochaineJour = computed(() => (prochaines.value.length ? prochaines.value[0].periodes[0].dateDebut : null))
const filtreService = ref(null) // null = tous les services
const groupes = computed(() => groupByService(garde.value))
const groupesAffiches = computed(() => (filtreService.value === null ? groupes.value : groupes.value.filter((g) => g.id === filtreService.value)))

async function load() {
  try {
    const [urgences, gardes] = await Promise.all([get('/numeros-urgence'), get('/gardes', { date: today() })])
    numeros.value = urgences.data
    garde.value = peopleOnDuty(gardes.data)
    total.value = garde.value.length
    if (filtreService.value !== null && !garde.value.some((p) => (p.service?.id ?? '') === filtreService.value)) filtreService.value = null
    prochaines.value = garde.value.length ? [] : peopleOnDuty((await get('/gardes/prochaines', { date: today() })).data)
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
          <Icon class="ico-red" name="plus" /> Numéros d'urgence <span class="count">{{ numeros.length }}</span>
          <Icon :name="panel === 'urgences' ? 'chevron-up' : 'chevron-down'" />
        </button>
        <button class="bar-btn" :aria-expanded="panel === 'garde'" @click="toggle('garde')">
          <span class="dot" aria-hidden="true"></span> Garde en cours <span class="count">{{ total }}</span>
          <Icon :name="panel === 'garde' ? 'chevron-up' : 'chevron-down'" />
        </button>
      </div>

      <div v-if="panel === 'urgences'" class="urgence-panel">
        <div class="panel-head">
          <h2>Numéros d'urgence</h2>
          <button v-if="auth.isAdmin" class="manage-btn" :aria-pressed="managing" @click="toggleManaging">
            <Icon v-if="!managing" name="settings" /> {{ managing ? 'Terminer' : 'Gérer' }}
          </button>
        </div>
        <p v-if="failed" class="muted">Indisponibles pour le moment.</p>
        <ul v-else class="urgence-list">
          <li v-for="(n, i) in numeros" :key="n.id" :class="{ managed: managing }" :style="{ '--i': Math.min(i, 12) }">
            <a :href="tel(n.numero)" class="urgence-num">
              <span class="urgence-ico"><Icon :name="iconOf(n)" /></span>
              <span class="urgence-label">{{ n.libelle }}</span>
              <b>{{ n.numero }}</b>
            </a>
            <span v-if="managing" class="row-actions">
              <button :aria-label="`Modifier ${n.libelle}`" title="Modifier" @click="edit(n)"><Icon name="edit" /></button>
              <button class="danger" :aria-label="`Supprimer ${n.libelle}`" title="Supprimer" @click="remove(n)"><Icon name="close" /></button>
            </span>
          </li>
        </ul>

        <template v-if="managing">
          <form v-if="editing !== null" class="urgence-form" @submit.prevent="save">
            <strong>{{ editing === 0 ? 'Nouveau numéro' : 'Modifier le numéro' }}</strong>
            <input v-model="form.libelle" maxlength="50" placeholder="Libellé" aria-label="Libellé" required />
            <input v-model="form.numero" maxlength="50" placeholder="Numéro" aria-label="Numéro" required inputmode="tel" />
            <label class="urgence-icone">
              <span class="urgence-icone-preview" aria-hidden="true"><Icon :name="iconOf({ libelle: form.libelle, icone: form.icone })" /></span>
              <select v-model="form.icone" aria-label="Icône">
                <option value="">Automatique (selon le libellé)</option>
                <option v-for="i in URGENCE_ICONS" :key="i.value" :value="i.value">{{ i.label }}</option>
              </select>
            </label>
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
            <Icon v-if="!gManaging" name="settings" /> {{ gManaging ? 'Terminer' : 'Gérer' }}
          </button>
        </div>
        <p v-if="failed" class="muted">Indisponible pour le moment.</p>
        <template v-else-if="!garde.length">
          <p class="muted">Aucune garde aujourd'hui.</p>
          <div v-if="prochaines.length" class="garde-next">
            <p class="garde-next-title">Prochaine garde, {{ relativeDay(prochaineJour, today()) }}</p>
            <ul>
              <li v-for="p in prochaines" :key="p.id">
                <b>{{ p.libelle }}</b>
                <span class="muted">{{ p.service?.libelle ? `${p.service.libelle} · ` : '' }}jusqu'au {{ frDate(p.periodes[0].dateFin) }}</span>
              </li>
            </ul>
          </div>
        </template>
        <div v-else class="garde-groups" :class="{ managing: gManaging }">
          <div v-if="groupes.length > 1" class="garde-filter" role="group" aria-label="Filtrer par service">
            <button type="button" class="garde-chip" :aria-pressed="filtreService === null" @click="filtreService = null">Tous</button>
            <button v-for="g in groupes" :key="g.id" type="button" class="garde-chip" :aria-pressed="filtreService === g.id" @click="filtreService = g.id">
              {{ g.libelle }} <span class="count">{{ g.people.length }}</span>
            </button>
          </div>
          <section v-for="g in groupesAffiches" :key="g.id" class="garde-group">
            <h3 class="garde-group-title">{{ g.libelle }}</h3>
            <ul v-if="!gManaging" class="garde-rows">
              <li v-for="p in g.people" :key="p.id" class="garde-row">
                <span class="avatar" aria-hidden="true">{{ initials(p.libelle) }}</span>
                <div class="garde-row-who">
                  <b>{{ p.libelle }}</b>
                  <span v-if="p.metier" class="muted">{{ p.metier.libelle }}</span>
                </div>
                <div class="garde-row-nums">
                  <span v-for="n in p.numerosGarde" :key="n.id" class="garde-row-num" :class="{ main: n.type === 'DECT' }">
                    <small>{{ n.type }}</small> <b>{{ n.numero }}</b>
                  </span>
                </div>
              </li>
            </ul>
            <ul v-else class="garde-list">
              <li v-for="p in g.people" :key="p.id" class="garde-item">
                <span class="avatar" aria-hidden="true">{{ initials(p.libelle) }}</span>
                <span class="garde-name">{{ p.libelle }}</span>
                <div class="garde-details">
                  <span class="garde-tags">
                    <span v-if="p.metier" class="garde-tag alt">{{ p.metier.libelle }}</span>
                  </span>
                  <span class="garde-nums">
                    <span v-for="n in p.numerosGarde" :key="n.id" class="chip-wrap">
                      <span class="garde-num"><Icon name="phone" /><span>{{ n.type }}</span><b>{{ n.numero }}</b></span>
                      <button class="mini" :aria-label="`Modifier ${n.type} ${n.numero}`" title="Modifier" @click="gEditNumero(p, n)"><Icon name="edit" /></button>
                      <button class="mini danger" :aria-label="`Supprimer ${n.type} ${n.numero}`" title="Supprimer" @click="gRemoveNumero(n)"><Icon name="close" /></button>
                    </span>
                    <button class="mini add" @click="gEditNumero(p, null)">+ numéro</button>
                  </span>
                </div>
                <span class="person-actions">
                  <button class="mini" @click="gEditPerson(p)"><Icon name="edit" /> Modifier</button>
                  <button class="mini danger" @click="gRemovePerson(p)"><Icon name="close" /> Retirer de la garde</button>
                </span>
              </li>
            </ul>
          </section>
        </div>

        <template v-if="gManaging">
          <form v-if="gForm" class="urgence-form" @submit.prevent="gSave">
            <template v-if="gForm.kind === 'personne'">
              <strong>Modifier la personne (compte AD)</strong>
              <AdPicker v-model="gForm.username" :max="50" />
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
          <button v-else class="add-btn" @click="router.push({ name: 'admin', params: { resource: 'gardes' } })">Planifier une garde</button>
          <p v-if="gError" class="error">{{ gError }}</p>
        </template>
      </div>
    </section>
  </div>
</template>
