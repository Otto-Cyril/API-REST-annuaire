<script setup>
// Texte avec les occurrences de la recherche entourées de <mark> (pas de v-html : le texte reste échappé).
import { computed } from 'vue'
import { splitMatch } from '../list'

const props = defineProps({ text: String, query: String })
const parts = computed(() => splitMatch(props.text ?? '', props.query ?? ''))
</script>

<template>
  <template v-for="(p, i) in parts" :key="i"><mark v-if="p.match">{{ p.text }}</mark><template v-else>{{ p.text }}</template></template>
</template>
