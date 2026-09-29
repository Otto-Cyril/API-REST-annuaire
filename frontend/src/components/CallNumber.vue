<script setup>
import { ref, onBeforeUnmount } from 'vue'

const props = defineProps({ numero: Object, large: Boolean })

const copied = ref(false)
let timer

const tel = (n) => `tel:${n.replace(/\s/g, '')}`

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
    <a :href="tel(numero.numero)" class="call-btn" :aria-label="`Appeler ${numero.type} ${numero.numero}`">
      <span class="call-ico" aria-hidden="true">✆</span>
      <span class="call-type">{{ numero.type }}</span>
      <b>{{ numero.numero }}</b>
    </a>
    <button type="button" class="copy-btn" :class="{ done: copied }" :aria-label="`Copier ${numero.numero}`" :title="copied ? 'Copié' : 'Copier le numéro'" @click="copy">
      {{ copied ? '✓' : '⧉' }}
    </button>
    <span class="sr-only" aria-live="polite">{{ copied ? 'Numéro copié' : '' }}</span>
  </span>
</template>
