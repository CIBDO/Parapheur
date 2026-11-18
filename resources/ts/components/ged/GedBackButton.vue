<script setup lang="ts">
const props = withDefaults(defineProps<{
  /** Route nommée de secours (hub GED par défaut). */
  to?: string
  /** Si true : tenter l’historique, sinon fallback `to`. */
  preferHistory?: boolean
}>(), {
  to: 'ged',
  preferHistory: false,
})

const router = useRouter()

const goBack = () => {
  if (props.preferHistory && window.history.length > 1) {
    router.back()

    return
  }
  router.push({ name: props.to })
}
</script>

<template>
  <VBtn
    icon
    variant="text"
    class="align-self-center"
    aria-label="Retour"
    @click="goBack"
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
