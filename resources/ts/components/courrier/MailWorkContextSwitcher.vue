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

const isMobile = useMediaQuery('(max-width: 960px)')

const currentLabel = computed(() => {
  const code = current.value?.structure?.code
  const name = current.value?.structure?.name || code
  const unit = current.value?.unit?.name
  if (!name)
    return ''

  // Mobile : privilégier le code court pour éviter le débordement dans la navbar
  if (isMobile.value && code)
    return code

  if (unit && unit !== name)
    return `${name} — ${unit}`

  return name
})

const currentFullLabel = computed(() => {
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
    v-if="!isMobile && (showSelect || currentLabel)"
    class="mail-context d-flex align-center me-2"
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
      class="mail-context-select"
      :title="currentFullLabel"
      @update:model-value="onSwitch"
    >
      <template #selection="{ item }">
        <span class="mail-context-label">{{ item.title }}</span>
      </template>
    </VSelect>
    <VChip
      v-else
      class="mail-context-chip"
      size="small"
      variant="tonal"
      color="primary"
      prepend-icon="tabler-building"
      :title="currentFullLabel"
    >
      <span class="mail-context-label">{{ currentLabel }}</span>
    </VChip>
  </div>
</template>

<style scoped>
.mail-context {
  flex: 0 1 auto;
  max-width: min(28rem, calc(100vw - 13.5rem));
  min-width: 0;
}

.mail-context-label {
  display: block;
  overflow: hidden;
  max-width: 100%;
  text-align: start;
  text-overflow: ellipsis;
  white-space: nowrap;
  line-height: 1.25;
}

.mail-context-chip {
  max-width: 100%;
  min-width: 0;
}

.mail-context-chip :deep(.v-chip__content) {
  min-width: 0;
  overflow: hidden;
}

.mail-context-select {
  min-width: 0;
  max-width: 100%;
  width: min(18rem, 100%);
}

.mail-context-select :deep(.v-field__input) {
  flex-wrap: nowrap;
  overflow: hidden;
}

@media (max-width: 960px) {
  .mail-context {
    max-width: calc(100vw - 11.5rem);
  }

  .mail-context-select {
    width: min(9.5rem, 100%);
  }
}
</style>
