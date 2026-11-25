<script setup lang="ts">
/**
 * Shell de route imbriquée : le layout Vuexy (nav) est appliqué
 * aux pages feuilles (index, nouvelle, [id], …) via setupLayouts.
 * Ce parent ne doit contenir que le RouterView.
 *
 * navActiveLink : active l’entrée « Réunions » du menu pour toutes les
 * sous-pages hors liste (nouvelle, détail, séance, …).
 */
definePage({
  // Shell parent uniquement (RouterView). Navigation = `parapheur-reunions` (index.vue).
  // `name: false` exige unplugin-vue-router ≥ 0.15 ; ici 0.8.8 → littéral string.
  name: 'parapheur-reunions-shell',
  meta: {
    navActiveLink: 'parapheur-reunions',
  },
})
</script>

<template>
  <RouterView />
</template>
