<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { useWorkspaceHome } from '@/composables/useWorkspace'
import { formatBytes } from '@/utils/workspaceUi'
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
const page = ref(1)
const total = ref(0)

async function load() {
  await loadHome()
  if (!workspace.value?.id)
    return
  loading.value = true
  try {
    const res = await $api(`/workspace/${workspace.value.id}/documents?per_page=25&page=${page.value}`)
    items.value = res.data || res.items || []
    total.value = res.total || items.value.length
  }
  finally {
    loading.value = false
  }
}

onMounted(load)
watch(page, load)

const headers = [
  { title: 'Document', key: 'object' },
  { title: 'Taille', key: 'size' },
  { title: 'Modifié', key: 'updated_at' },
]
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Mes documents"
      subtitle="Tous les fichiers de mon espace personnel"
      icon="tabler-files"
    />
    <VCard class="parapheur-section-card">
      <VDataTableServer
        v-model:page="page"
        :headers="headers"
        :items="items"
        :items-length="total"
        :loading="loading"
        :items-per-page="25"
      >
        <template #item.object="{ item }">
          <RouterLink
            class="text-primary text-decoration-none"
            :to="{ name: 'espace-documents-id', params: { id: item.id } }"
          >
            {{ item.title || item.object }}
          </RouterLink>
        </template>
        <template #item.size="{ item }">
          {{ formatBytes(item.latest_version?.size) }}
        </template>
        <template #item.updated_at="{ item }">
          {{ formatDateFr(item.updated_at) }}
        </template>
      </VDataTableServer>
    </VCard>
  </div>
</template>
