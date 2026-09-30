<script setup>
// Barre de recherche, listes service / métier / tri et pastilles des filtres actifs.
// `filters` est l'objet réactif de useDirectory : les champs le modifient directement.
import Icon from './Icon.vue'

defineProps({
  sortable: { type: Boolean, default: true }, // false : pas de liste « Trier par » (tri par nom imposé)
  filters: Object,
  services: Array,
  metiers: Array,
  hasFilters: Boolean,
  serviceLabel: String,
  metierLabel: String,
})
defineEmits(['reset', 'submit'])
</script>

<template>
  <form class="filters" :class="{ 'no-sort': !sortable }" @submit.prevent="$emit('submit')">
    <div class="search">
      <input v-model="filters.q" type="search" maxlength="50" placeholder="Rechercher (nom, service, métier…)" aria-label="Rechercher" />
      <button v-if="filters.q" type="button" class="clear" aria-label="Effacer la recherche" @click="filters.q = ''"><Icon name="close" /></button>
    </div>
    <select v-model="filters.serviceId" aria-label="Service">
      <option value="">Tous les services</option>
      <option v-for="s in services" :key="s.id" :value="String(s.id)">{{ s.libelle }}</option>
    </select>
    <select v-model="filters.metierId" aria-label="Métier">
      <option value="">Tous les métiers</option>
      <option v-for="m in metiers" :key="m.id" :value="String(m.id)">{{ m.libelle }}</option>
    </select>
    <select v-if="sortable" v-model="filters.sort" aria-label="Trier par">
      <option value="nom">Trier par nom</option>
      <option value="service">Trier par service</option>
    </select>
  </form>

  <div v-if="hasFilters" class="active-filters">
    <span class="muted">Filtres :</span>
    <button v-if="filters.q" type="button" class="filter-pill" @click="filters.q = ''">« {{ filters.q }} » <Icon name="close" /></button>
    <button v-if="serviceLabel" type="button" class="filter-pill" @click="filters.serviceId = ''">{{ serviceLabel }} <Icon name="close" /></button>
    <button v-if="metierLabel" type="button" class="filter-pill" @click="filters.metierId = ''">{{ metierLabel }} <Icon name="close" /></button>
    <button type="button" class="link" @click="$emit('reset')">Tout effacer</button>
  </div>
</template>
