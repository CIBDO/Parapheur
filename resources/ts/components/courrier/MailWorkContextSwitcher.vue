<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { $api } from '@/utils/api'

const userData = useCookie<Record<string, any> | null>('userData')
const switching = ref(false)

const options = computed(() => userData.value?.mailContext?.options ?? [])
const current = computed(() => userData.value?.mailContext?.current ?? null)
const selectable = computed(() => (options.value as Record<string, any>[])
  .filter(option => option.assignment_id)
  .map(option => ({ ...option, title: optionTitle(option) })))
const showSelect = computed(() => selectable.value.length > 1)

const currentLabel = computed(() => {
  const structure = current.value?.structure?.name || current.value?.structure?.code
  const unit = current.value?.unit?.name
  if (!structure)
    return ''
  if (unit && unit !== structure)
    return `${structure} — ${unit}`

  return structure
})

function optionTitle(option: Record<string, any>) {
  const structure = option.structure?.name || option.structure?.code || 'Structure'
  const unit = option.unit?.name && option.unit.name !== structure ? ` — ${option.unit.name}` : ''
  const scope = option.scope_label ? ` (${option.scope_label})` : ''

  return `${structure}${unit}${scope}`
}

async function loadContext() {
  if (userData.value?.mailContext)
    return

  try {
    const data = await $api('/mail/work-context')
    if (userData.value)
      userData.value = { ...userData.value, mailContext: data }
  }
  catch {
    // Le périmètre reste celui connu au login.
  }
}

async function onSwitch(assignmentId: number | null) {
  if (!assignmentId || assignmentId === current.value?.assignment_id)
    return

  switching.value = true
  try {
    const data = await $api('/mail/work-context', {
      method: 'POST',
      body: { assignment_id: assignmentId },
    })
    if (userData.value)
      userData.value = { ...userData.value, mailContext: data }
    window.location.reload()
  }
  finally {
    switching.value = false
  }
}

onMounted(loadContext)
</script>

<template>
  <div
    v-if="showSelect || currentLabel"
    class="d-flex align-center me-2"
    style="max-width: 320px;"
  >
    <VSelect
      v-if="showSelect"
      :model-value="current?.assignment_id"
      :items="selectable"
      item-title="title"
      item-value="assignment_id"
      density="compact"
      variant="outlined"
      hide-details
      :loading="switching"
      :disabled="switching"
      label="Périmètre"
      style="min-width: 220px;"
      @update:model-value="onSwitch"
    />
    <VChip
      v-else
      size="small"
      variant="tonal"
      color="primary"
      prepend-icon="tabler-building"
    >
      {{ currentLabel }}
    </VChip>
  </div>
</template>
