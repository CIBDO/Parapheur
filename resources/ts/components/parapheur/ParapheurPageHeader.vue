<script setup lang="ts">
defineProps<{
  title: string
  subtitle?: string
  icon?: string
}>()
</script>

<template>
  <div class="parapheur-page-header d-flex flex-wrap justify-space-between align-start gap-4 mb-6">
    <div class="d-flex align-start gap-3 min-w-0">
      <VAvatar
        v-if="icon"
        color="primary"
        variant="tonal"
        rounded
        size="48"
        class="flex-shrink-0"
      >
        <VIcon
          :icon="icon"
          size="28"
        />
      </VAvatar>
      <div class="min-w-0">
        <h4 class="text-h4 mb-1 text-high-emphasis">
          {{ title }}
        </h4>
        <p
          v-if="subtitle"
          class="text-body-1 mb-0 text-medium-emphasis"
        >
          {{ subtitle }}
        </p>
        <slot name="subtitle" />
      </div>
    </div>

    <div
      v-if="$slots.actions || $slots.default"
      class="d-flex flex-wrap align-center gap-2"
    >
      <slot name="actions">
        <slot />
      </slot>
    </div>
  </div>
</template>
