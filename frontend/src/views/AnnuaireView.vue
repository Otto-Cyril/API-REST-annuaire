<script setup>
import { computed } from 'vue'
import Pagination from '../components/Pagination.vue'
import CallNumber from '../components/CallNumber.vue'
import DirectoryFilters from '../components/DirectoryFilters.vue'
import Icon from '../components/Icon.vue'
import Highlight from '../components/Highlight.vue'
import { useDirectory } from '../composables/useDirectory'
import { useAuth } from '../stores/auth'
import { adEditUrl } from '../adLink'
import { withServiceHeaders } from '../list'

const auth = useAuth()

// L'annuaire du personnel est lu directement dans l'AD (lecture seule) : services et postes viennent de l'AD.
const { filters, page, list, meta, services, metiers, loading, error, hasFilters, resetFilters, serviceLabel, metierLabel, countLabel, load } =
  useDirectory('/personnes', { requireFilter: true, relevance: true, servicesPath: '/personnes/services', metiersPath: '/personnes/metiers' })

const fullName = (p) => [p.prenom, p.nom].filter(Boolean).join(' ') || p.username

// Tri par service : un en-tête par service dans la liste
const rows = computed(() => (filters.sort === 'service' ? withServiceHeaders(list.value, (p) => p.service ?? { id: '', libelle: 'Sans service' }) : list.value))
const showCount = computed(() => hasFilters.value && !error.value && !(loading.value && !list.value.length))
</script>

<template>
  <h1>Annuaire du personnel</h1>

  <DirectoryFilters
    :filters="filters"
    relevance
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
    <p class="muted">Saisissez un nom, ou choisissez un service ou un métier, pour afficher le personnel.</p>
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
    <template v-for="p in rows" :key="p.username ?? p.id">
    <li v-if="p.header" class="group-title">{{ p.label }}</li>
    <li v-else class="card person">
      <div class="person-body">
        <RouterLink :to="{ name: 'personne', params: { username: p.username } }" class="card-title"><Highlight :text="fullName(p)" :query="filters.q" /></RouterLink>
        <div class="tags">
          <span v-if="p.service" class="tag tag-service"><Highlight :text="p.service.libelle" :query="filters.q" /></span>
          <span v-if="p.metier" class="tag tag-metier"><Highlight :text="p.metier.libelle" :query="filters.q" /></span>
        </div>
      </div>
      <div class="call-list">
        <CallNumber v-for="n in p.numeros" :key="n.numero" :numero="n" />
        <a v-if="p.email" :href="`mailto:${p.email}`" class="mail-btn" :aria-label="`Écrire à ${fullName(p)}`">
          <Icon name="mail" /> {{ p.email }}
        </a>
        <a
          v-if="auth.isAdmin && adEditUrl(p.username)"
          :href="adEditUrl(p.username)"
          target="_blank"
          rel="noopener"
          class="edit-btn"
          :aria-label="`Modifier la fiche de ${fullName(p)} dans l'AD`"
          title="Modifier dans l'AD"
        ><Icon name="edit" /></a>
      </div>
    </li>
    </template>
  </ul>

  <Pagination v-if="hasFilters" :page="page" :total-pages="meta.totalPages" :total="meta.total" @change="page = $event" />
</template>
