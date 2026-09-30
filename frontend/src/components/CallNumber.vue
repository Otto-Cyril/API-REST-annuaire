<script setup>
import { ref, onBeforeUnmount } from 'vue'
import Icon from './Icon.vue'

const props = defineProps({ numero: Object, large: Boolean })

const copied = ref(false)
let timer

async function copy() {
  const text = props.numero.numero
  try {
    await navigator.clipboard.writeText(text)
  } catch {
    // Repli pour les pages servies en http hors localhost, où l'API Clipboard est indisponible.
    const ta = document.createElement('textarea')
    ta.value = text
    ta.style.position = 'fixed'
    ta.style.opacity = '0'
    document.body.appendChild(ta)
    ta.select()
    try {
      document.execCommand('copy')
    } finally {
      ta.remove()
    }
  }
  copied.value = true
  clearTimeout(timer)
  timer = setTimeout(() => (copied.value = false), 1500)
}

onBeforeUnmount(() => clearTimeout(timer))
</script>

<template>
  <span class="call-item" :class="{ large }">
    <span class="call-btn">
      <Icon class="call-ico" name="phone" />
      <span class="call-type">{{ numero.type }}</span>
      <b>{{ numero.numero }}</b>
    </span>
    <button type="button" class="copy-btn" :class="{ done: copied }" :aria-label="`Copier ${numero.numero}`" :title="copied ? 'Copié' : 'Copier le numéro'" @click="copy">
      <Icon :name="copied ? 'check' : 'copy'" />
    </button>
    <span class="sr-only" aria-live="polite">{{ copied ? 'Numéro copié' : '' }}</span>
  </span>
</template>
