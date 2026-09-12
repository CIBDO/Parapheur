<script setup lang="ts">
const shortcuts = [
  {
    icon: 'tabler-briefcase',
    title: 'Parapheur',
    subtitle: 'Dossiers à traiter',
    to: { name: 'parapheur' },
  },
  {
    icon: 'tabler-calendar-event',
    title: 'Agenda',
    subtitle: 'Rendez-vous',
    to: { name: 'parapheur-agenda' },
  },
  {
    icon: 'tabler-users-group',
    title: 'Réunions',
    subtitle: 'Séances & décisions',
    to: { name: 'parapheur-reunions' },
  },
  {
    icon: 'tabler-file-plus',
    title: 'Nouveau dossier',
    subtitle: 'Déposer un document',
    to: { name: 'parapheur-nouveau' },
  },
]
</script>

<template>
  <Shortcuts :shortcuts="shortcuts" />
</template>
