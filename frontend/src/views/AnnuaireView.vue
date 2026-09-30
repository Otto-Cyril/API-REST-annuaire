<script setup>
import Pagination from '../components/Pagination.vue'
import CallNumber from '../components/CallNumber.vue'
import DirectoryFilters from '../components/DirectoryFilters.vue'
import Icon from '../components/Icon.vue'
import { useDirectory } from '../composables/useDirectory'

const { filters, page, list, meta, services, metiers, loading, error, hasFilters, resetFilters, serviceLabel, metierLabel, countLabel, load } =
  useDirectory('/personnes')

const fullName = (p) => `${p.prenom} ${p.nom}`
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
    @reset="resetFilters"
    @submit="load"
  />

  <p v-if="!error && !(loading && !list.length)" class="result-count muted" aria-live="polite">{{ countLabel }}</p>

  <div v-if="error" class="error-box" role="alert">
    <p class="error">{{ error }}</p>
    <button type="button" @click="load">Réessayer</button>
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
    <li v-for="p in list" :key="p.id" class="card person">
      <div class="person-body">
        <span class="card-title">{{ fullName(p) }}</span>
        <div class="tags">
          <span class="tag tag-service">{{ p.service.libelle }}</span>
          <span class="tag tag-metier">{{ p.metier.libelle }}</span>
          <span v-if="p.service.localisation" class="muted tag-loc">{{ p.service.localisation }}</span>
        </div>
      </div>
      <div class="call-list">
        <CallNumber v-if="p.telephone" :numero="{ numero: p.telephone, type: 'Tél.' }" />
        <CallNumber v-if="p.dect" :numero="{ numero: p.dect, type: 'DECT' }" />
        <a v-if="p.email" :href="`mailto:${p.email}`" class="mail-btn" :aria-label="`Écrire à ${fullName(p)}`">
          <Icon name="mail" /> {{ p.email }}
        </a>
      </div>
    </li>
  </ul>

  <Pagination :page="page" :total-pages="meta.totalPages" :total="meta.total" @change="page = $event" />
</template>
