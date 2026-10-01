<script setup>
import Pagination from '../components/Pagination.vue'
import CallNumber from '../components/CallNumber.vue'
import DirectoryFilters from '../components/DirectoryFilters.vue'
import Icon from '../components/Icon.vue'
import Highlight from '../components/Highlight.vue'
import { useAuth } from '../stores/auth'
import { useDirectory } from '../composables/useDirectory'
import { withServiceHeaders } from '../list'
import { computed } from 'vue'

const auth = useAuth()
const { filters, page, list, meta, services, metiers, loading, error, hasFilters, resetFilters, serviceLabel, metierLabel, countLabel, load } =
  useDirectory('/personnes', { requireFilter: true })

const fullName = (p) => `${p.prenom} ${p.nom}`

// Tri par service : un en-tête par service dans la liste
const rows = computed(() => (filters.sort === 'service' ? withServiceHeaders(list.value, (p) => p.service) : list.value))
const showCount = computed(() => hasFilters.value && !error.value && !(loading.value && !list.value.length))
</script>

<template>
  <h1>Annuaire du personnel</h1>

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
    <template v-for="p in rows" :key="p.id">
    <li v-if="p.header" class="group-title">{{ p.label }}</li>
    <li v-else class="card person">
      <div class="person-body">
        <span class="card-title"><Highlight :text="fullName(p)" :query="filters.q" /></span>
        <div class="tags">
          <span class="tag tag-service"><Highlight :text="p.service.libelle" :query="filters.q" /></span>
          <span class="tag tag-metier"><Highlight :text="p.metier.libelle" :query="filters.q" /></span>
          <span v-if="p.service.localisation" class="muted tag-loc">{{ p.service.localisation }}</span>
        </div>
      </div>
      <div class="call-list">
        <CallNumber v-if="p.telephone" :numero="{ numero: p.telephone, type: 'Tél.' }" />
        <CallNumber v-if="p.dect" :numero="{ numero: p.dect, type: 'DECT' }" />
        <a v-if="p.email" :href="`mailto:${p.email}`" class="mail-btn" :aria-label="`Écrire à ${fullName(p)}`">
          <Icon name="mail" /> {{ p.email }}
        </a>
        <RouterLink
          v-if="auth.isAdmin"
          :to="{ name: 'personne-edit', params: { id: p.id } }"
          class="edit-btn"
          :aria-label="`Modifier la fiche de ${fullName(p)}`"
          title="Modifier la fiche"
        ><Icon name="edit" /></RouterLink>
      </div>
    </li>
    </template>
  </ul>

  <Pagination v-if="hasFilters" :page="page" :total-pages="meta.totalPages" :total="meta.total" @change="page = $event" />
</template>
