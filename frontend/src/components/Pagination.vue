<script setup>
import { computed } from 'vue'

const props = defineProps({ page: Number, totalPages: Number, total: Number })
defineEmits(['change'])

// Numéros affichés : première, dernière, page courante ± 1, avec des « … » entre les trous.
const pages = computed(() => {
  const keep = new Set([1, props.totalPages, props.page - 1, props.page, props.page + 1])
  const out = []
  let last = 0
  for (const n of [...keep].filter((n) => n >= 1 && n <= props.totalPages).sort((a, b) => a - b)) {
    if (n - last > 1) out.push('…')
    out.push(n)
    last = n
  }
  return out
})
</script>

<template>
  <nav v-if="totalPages > 1" class="pagination" aria-label="Pagination">
    <button :disabled="page <= 1" @click="$emit('change', page - 1)">Précédent</button>
    <template v-for="(n, i) in pages" :key="i">
      <span v-if="n === '…'" class="muted" aria-hidden="true">…</span>
      <button
        v-else
        class="page-btn"
        :class="{ current: n === page }"
        :aria-current="n === page ? 'page' : undefined"
        :aria-label="`Page ${n}`"
        @click="$emit('change', n)"
      >
        {{ n }}
      </button>
    </template>
    <button :disabled="page >= totalPages" @click="$emit('change', page + 1)">Suivant</button>
    <span class="muted page-total">{{ total }} résultats</span>
  </nav>
</template>
