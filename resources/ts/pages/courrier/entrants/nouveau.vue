<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import CorrespondenceCreateDialog from '@/components/courrier/CorrespondenceCreateDialog.vue'

definePage({
  meta: {
    layout: 'default',
    action: 'create',
    subject: 'Courrier',
  },
})

const router = useRouter()
const open = ref(true)

function onCreated(item: { id: number }) {
  router.replace({ name: 'courrier-entrants-id', params: { id: item.id } })
}

function onToggle(value: boolean) {
  open.value = value
  if (!value)
    router.replace({ name: 'courrier-entrants' })
}
</script>

<template>
  <CorrespondenceCreateDialog
    :model-value="open"
    direction="entrant"
    @update:model-value="onToggle"
    @created="onCreated"
  />
</template>
