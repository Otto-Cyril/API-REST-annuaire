<script setup>
import Pagination from '../components/Pagination.vue'
import CallNumber from '../components/CallNumber.vue'
import DirectoryFilters from '../components/DirectoryFilters.vue'
import Icon from '../components/Icon.vue'
import { useAuth } from '../stores/auth'
import { useDirectory } from '../composables/useDirectory'

const auth = useAuth()
const { filters, page, list, meta, overall, services, metiers, loading, error, hasFilters, resetFilters, serviceLabel, metierLabel, countLabel, load } =
  useDirectory('/personnel', { requireFilter: true })
</script>

<template>
  <h1>Personnel de garde</h1>

  <section class="stats" aria-label="Chiffres clés">
    <button type="button" class="stat" :class="{ active: !hasFilters }" @click="resetFilters">
      <b>{{ overall ?? meta.total }}</b><span>Personnel</span>
    </button>
    <div class="stat"><b>{{ services.length }}</b><span>Services</span></div>
    <div class="stat"><b>{{ metiers.length }}</b><span>Métiers</span></div>
  </section>

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

  <p v-if="hasFilters && !error && !(loading && !list.length)" class="result-count muted" aria-live="polite">{{ countLabel }}</p>

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
    <li v-for="p in list" :key="p.id" class="card person">
      <div class="person-body">
        <RouterLink :to="{ name: 'fiche', params: { id: p.id } }" class="card-title">{{ p.libelle }}</RouterLink>
        <div class="tags">
          <span class="tag tag-service">{{ p.service.libelle }}</span>
          <span class="tag tag-metier">{{ p.metier.libelle }}</span>
          <span v-if="p.service.localisation" class="muted tag-loc">{{ p.service.localisation }}</span>
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
  </ul>

  <Pagination v-if="hasFilters" :page="page" :total-pages="meta.totalPages" :total="meta.total" @change="page = $event" />
</template>
