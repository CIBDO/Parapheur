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
  meta: {
    navActiveLink: 'parapheur-reunions',
  },
})
</script>

<template>
  <RouterView />
</template>
