<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { useWorkspaceHome } from '@/composables/useWorkspace'
import { formatDateFr } from '@/utils/parapheurUi'

definePage({
  meta: {
    layout: 'default',
    action: 'read',
    subject: 'Workspace',
  },
})

const { workspace, load: loadHome } = useWorkspaceHome()
const loading = ref(true)
const items = ref<any[]>([])

async function load() {
  await loadHome()
  if (!workspace.value?.id)
    return
  loading.value = true
  try {
    const res = await $api(`/workspace/${workspace.value.id}/trash`)
    items.value = res.data || res.items || res || []
  }
  finally {
    loading.value = false
  }
}

async function restore(item: any) {
  await $api(`/workspace/${workspace.value.id}/trash/${item.id}/restore`, { method: 'POST', body: { type: item.type || 'document' } })
  await load()
}

onMounted(load)
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Corbeille"
      subtitle="Éléments supprimés — restauration possible"
      icon="tabler-trash"
    />
    <VCard class="parapheur-section-card">
      <VList v-if="items.length">
        <VListItem
          v-for="item in items"
          :key="`${item.type}-${item.id}`"
          :title="item.name || item.title || item.object"
          :subtitle="formatDateFr(item.deleted_at)"
          :prepend-icon="item.type === 'folder' ? 'tabler-folder' : 'tabler-file'"
        >
          <template #append>
            <VBtn
              size="small"
              variant="tonal"
              @click="restore(item)"
            >
              Restaurer
            </VBtn>
          </template>
        </VListItem>
      </VList>
      <VCardText
        v-else
        class="text-medium-emphasis"
      >
        {{ loading ? 'Chargement…' : 'Corbeille vide.' }}
      </VCardText>
    </VCard>
  </div>
</template>
