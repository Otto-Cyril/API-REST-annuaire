<script setup>
// Tableau des effectifs (un service ou un métier par ligne) : filtre texte, tri par effectif ou par nom, un clic ouvre l'annuaire filtré.
import { computed, ref } from 'vue'

const props = defineProps({
  title: String,
  queryKey: String, // paramètre de l'annuaire à renseigner : « service » ou « metier »
  items: { type: Array, default: () => [] }, // [{ libelle, total }]
})

const search = ref('')
const sortBy = ref('total') // 'total' (décroissant) ou 'libelle' (alphabétique)

const fold = (s) => s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase()
const rows = computed(() => {
  const words = fold(search.value).split(/\s+/).filter(Boolean)
  const found = props.items.filter((i) => words.every((w) => fold(i.libelle).includes(w)))
  return sortBy.value === 'libelle'
    ? [...found].sort((a, b) => fold(a.libelle).localeCompare(fold(b.libelle)))
    : [...found].sort((a, b) => b.total - a.total || fold(a.libelle).localeCompare(fold(b.libelle)))
})
const sum = computed(() => rows.value.reduce((t, i) => t + i.total, 0))
const ariaSort = (key) => (sortBy.value === key ? (key === 'total' ? 'descending' : 'ascending') : 'none')
</script>

<template>
  <section class="ct" :aria-label="title">
    <header class="ct-head">
      <h2>{{ title }}</h2>
      <span class="muted">{{ search ? `${rows.length} sur ${items.length}` : items.length }} · {{ sum }} personnes</span>
    </header>
    <input v-model="search" type="search" maxlength="50" :placeholder="`Filtrer les ${title.toLowerCase()}`" :aria-label="`Filtrer les ${title.toLowerCase()}`" />

    <div class="ct-scroll">
      <table>
        <thead>
          <tr>
            <th scope="col" :aria-sort="ariaSort('libelle')"><button type="button" class="ct-sort" :class="{ on: sortBy === 'libelle' }" @click="sortBy = 'libelle'">{{ title.replace(/s$/, '') }}</button></th>
            <th scope="col" class="ct-num" :aria-sort="ariaSort('total')"><button type="button" class="ct-sort" :class="{ on: sortBy === 'total' }" @click="sortBy = 'total'">Personnes</button></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="i in rows" :key="i.libelle">
            <td><RouterLink :to="{ name: 'annuaire', query: { [queryKey]: i.libelle } }" :title="`Ouvrir l'annuaire : ${i.libelle}`">{{ i.libelle }}</RouterLink></td>
            <td class="ct-num">{{ i.total }}</td>
          </tr>
          <tr v-if="!rows.length"><td colspan="2" class="muted">Aucun résultat.</td></tr>
        </tbody>
      </table>
    </div>
  </section>
</template>
