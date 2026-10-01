<script setup>
// Icônes SVG « trait » (24×24), héritent la couleur du texte. Décoratives : le libellé reste dans le texte ou l'aria-label.
defineProps({ name: { type: String, required: true } })

const paths = {
  menu: '<path d="M4 6h16M4 12h16M4 18h16"/>',
  sun: '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
  moon: '<path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/>',
  mail: '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
  phone: '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/>',
  copy: '<rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V6a2 2 0 0 1 2-2h9"/>',
  check: '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
  'arrow-left': '<path d="M19 12H5M11 6l-6 6 6 6"/>',
  edit: '<path d="M4 20h4L19 9a2.8 2.8 0 0 0-4-4L4 16z"/><path d="m13.5 6.5 4 4"/>',
  close: '<path d="M6 6l12 12M18 6 6 18"/>',
  plus: '<path d="M12 5v14M5 12h14"/>',
  'chevron-down': '<path d="m6 9 6 6 6-6"/>',
  'chevron-up': '<path d="m6 15 6-6 6 6"/>',
  flame: '<path d="M12 3c1 3.5 5 5.500 5 10a5 5 0 0 1-10 0c0-2 1-3.500 2.500-4.500 0 2 .8 3 2 3 0-3-.5-5 .5-8.500z"/>',
  shield: '<path d="M12 3 5 6v5c0 5 3 8 7 10 4-2 7-5 7-10V6z"/>',
  trash: '<path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13M10 11v5M14 11v5"/>',
  user: '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/>',
  heart: '<path d="M12 20s-7-4.500-7-10a4 4 0 0 1 7-2.500A4 4 0 0 1 19 10c0 5.500-7 10-7 10z"/>',
  stethoscope: '<path d="M6 3v6a4 4 0 0 0 8 0V3M10 13v2a5 5 0 0 0 10 0v-1"/><circle cx="20" cy="12" r="2"/>',
  alert: '<path d="M12 3 2 20h20z"/><path d="M12 10v4M12 17.500v.01"/>',
  bed: '<path d="M3 18V6M3 14h18v4M21 14v-2a3 3 0 0 0-3-3h-7v5"/><circle cx="7" cy="11" r="1.500"/>',
  pill: '<rect x="3" y="9" width="18" height="6" rx="3" transform="rotate(-45 12 12)"/><path d="m8.500 8.500 7 7"/>',
  drop: '<path d="M12 3s6 6.500 6 11a6 6 0 0 1-12 0c0-4.500 6-11 6-11z"/>',
  building: '<path d="M5 21V4h9v17M14 9h5v12M3 21h18M8 8h3M8 12h3M8 16h3"/>',
  wrench: '<path d="M14.500 6.500a4 4 0 0 0 5 5L10 21a2.100 2.100 0 0 1-3-3z"/>',
  ambulance: '<path d="M3 16V7h11v9M14 10h4l3 3v3h-2"/><circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/><path d="M8.500 9v4M6.500 11h4"/>',
  computer: '<rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/>',
  virus: '<circle cx="12" cy="12" r="4"/><path d="M12 3v3M12 18v3M3 12h3M18 12h3M5.600 5.600l2.100 2.100M16.300 16.300l2.100 2.100M5.600 18.400l2.100-2.100M16.300 7.700l2.100-2.100"/><circle cx="12" cy="3" r=".5"/><circle cx="12" cy="21" r=".5"/><circle cx="3" cy="12" r=".5"/><circle cx="21" cy="12" r=".5"/>',
  baby: '<circle cx="12" cy="10" r="6"/><path d="M9.500 9.500h.01M14.500 9.500h.01M10 12.500a2.500 2.500 0 0 0 4 0M12 4c0-1.500 1-2 2-2M5 22c1-3.500 3.500-4 7-4s6 .5 7 4"/>',
  info: '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8v.01"/>',
  users: '<circle cx="9" cy="8" r="3.500"/><path d="M2 20c0-3.500 3-5.500 7-5.500s7 2 7 5.500M16 4.500a3.500 3.500 0 0 1 0 7M18 15c2.500.7 4 2.300 4 5"/>',
  settings:'<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9 7 7M17 17l2.1 2.1M4.9 19.1 7 17M17 7l2.1-2.1"/>',
}
</script>

<template>
  <svg class="icon" viewBox="0 0 24 24" width="1em" height="1em" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" v-html="paths[name]"></svg>
</template>
