<script setup>
defineProps({ page: Number, totalPages: Number, total: Number })
defineEmits(['change'])
</script>

<template>
  <nav v-if="totalPages > 1" class="pagination" aria-label="Pagination">
    <button :disabled="page <= 1" @click="$emit('change', page - 1)">Précédent</button>
    <span>Page {{ page }} / {{ totalPages }} ({{ total }} résultats)</span>
    <button :disabled="page >= totalPages" @click="$emit('change', page + 1)">Suivant</button>
  </nav>
</template>
