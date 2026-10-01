<script setup>
import Pagination from '../components/Pagination.vue'
import CallNumber from '../components/CallNumber.vue'
import DirectoryFilters from '../components/DirectoryFilters.vue'
import Icon from '../components/Icon.vue'
import Highlight from '../components/Highlight.vue'
import { ref, computed, onMounted } from 'vue'
import { get } from '../api'
import { today, peopleOnDuty } from '../garde'
import { withServiceHeaders } from '../list'
import { useAuth } from '../stores/auth'
import { useDirectory } from '../composables/useDirectory'

const auth = useAuth()
const { filters, page, list, meta, overall, services, metiers, loading, error, hasFilters, resetFilters, serviceLabel, metierLabel, countLabel, load } =
  useDirectory('/personnel', { requireFilter: true, servicesPath: '/personnel/services', metiersPath: '/personnel/metiers' })

// Tri par service : un en-tête par service dans la liste
const rows = computed(() => (filters.sort === 'service' ? withServiceHeaders(list.value, (p) => p.service ?? { id: '', libelle: 'Sans service' }) : list.value))
const showCount = computed(() => hasFilters.value && !error.value && !(loading.value && !list.value.length))

// Nombre de personnes de garde aujourd'hui (null tant que non chargé ou si l'API échoue : la carte est alors masquée)
const onDutyCount = ref(null)
onMounted(async () => {
  try {
    onDutyCount.value = peopleOnDuty((await get('/gardes', { date: today() })).data).length
  } catch {
    /* compteur indisponible */
  }
})
</script>

<template>
  <h1>Personnel de garde</h1>

  <section class="stats" aria-label="Chiffres clés">
    <button type="button" class="stat" :class="{ active: !hasFilters }" @click="resetFilters">
      <b>{{ overall ?? meta.total }}</b><span>Personnel</span>
    </button>
    <div v-if="onDutyCount !== null" class="stat"><b>{{ onDutyCount }}</b><span>De garde aujourd'hui</span></div>
    <div class="stat"><b>{{ services.length }}</b><span>Services</span></div>
  </section>

  <DirectoryFilters
    :filters="filters"
    :services="services"
    :metiers="metiers"
    :has-filters="hasFilters"
    :service-label="serviceLabel"
    :metier-label="metierLabel"
    :count="showCount ? countLabel : ''"
    @reset="resetFilters"
    @submit="load"
  />

  <div v-if="error" class="error-box" role="alert">
    <p class="error">{{ error }}</p>
    <button type="button" @click="load">Réessayer</button>
  </div>

  <div v-else-if="!hasFilters" class="empty">
    <p><b>Commencez votre recherche</b></p>
    <p class="muted">Saisissez un nom, ou choisissez un service ou un métier, pour afficher le personnel de garde.</p>
  </div>

  <ul v-else-if="loading && !list.length" class="cards" aria-busy="true" aria-label="Chargement">
    <li v-for="i in 4" :key="i" class="card skeleton" aria-hidden="true">
      <span class="sk-line w60"></span>
      <span class="sk-line w40"></span>
    </li>
  </ul>

  <div v-else-if="!list.length" class="empty">
    <p><b>Aucun résultat</b></p>
    <p class="muted">Aucune personne ne correspond à votre recherche.</p>
    <button v-if="hasFilters" type="button" @click="resetFilters">Réinitialiser les filtres</button>
  </div>

  <ul v-else class="cards" :class="{ busy: loading }">
    <template v-for="p in rows" :key="p.id">
    <li v-if="p.header" class="group-title">{{ p.label }}</li>
    <li v-else class="card person">
      <div class="person-body">
        <RouterLink :to="{ name: 'fiche', params: { id: p.id } }" class="card-title"><Highlight :text="p.libelle" :query="filters.q" /></RouterLink>
        <div class="tags">
          <span v-if="p.service" class="tag tag-service"><Highlight :text="p.service.libelle" :query="filters.q" /></span>
          <span v-if="p.metier" class="tag tag-metier"><Highlight :text="p.metier.libelle" :query="filters.q" /></span>
        </div>
      </div>
      <div class="call-list">
        <CallNumber v-for="n in p.numerosGarde" :key="n.id" :numero="n" />
        <RouterLink
          v-if="auth.isAdmin"
          :to="{ name: 'personnel-edit', params: { id: p.id } }"
          class="edit-btn"
          :aria-label="`Modifier la fiche de ${p.libelle}`"
          title="Modifier la fiche"
        ><Icon name="edit" /></RouterLink>
      </div>
    </li>
    </template>
  </ul>

  <Pagination v-if="hasFilters" :page="page" :total-pages="meta.totalPages" :total="meta.total" @change="page = $event" />
</template>
