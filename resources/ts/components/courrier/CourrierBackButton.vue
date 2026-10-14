<script setup lang="ts">
defineProps<{
  to: string
}>()
</script>

<template>
  <VBtn
    icon
    variant="text"
    class="align-self-center"
    :to="{ name: to }"
  >
    <VIcon
      icon="tabler-arrow-left"
      size="22"
    />
    <VTooltip
      activator="parent"
      location="bottom"
    >
      Retour
    </VTooltip>
  </VBtn>
</template>
