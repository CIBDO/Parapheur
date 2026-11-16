<script setup lang="ts">
const model = defineModel<string>({ default: '' })

defineProps<{
  currentUrl?: string | null
  loading?: boolean
}>()

const emit = defineEmits<{
  save: []
}>()
</script>

<template>
  <div>
    <strong>Lien visioconférence</strong>
    <div
      v-if="currentUrl"
      class="mb-2"
    >
      <a
        :href="currentUrl"
        target="_blank"
        rel="noopener noreferrer"
      >{{ currentUrl }}</a>
    </div>
    <div class="d-flex flex-wrap gap-2 align-center mt-1">
      <VTextField
        v-model="model"
        label="URL visio"
        density="compact"
        hide-details
        style="min-inline-size: 220px; flex: 1"
      />
      <VBtn
        size="small"
        variant="tonal"
        :loading="loading"
        @click="emit('save')"
      >
        Enregistrer
      </VBtn>
    </div>
  </div>
</template>
