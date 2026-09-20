<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { workspaceShareAbilityLabels } from '@/utils/workspaceUi'
import { formatDateFr, labelOf } from '@/utils/parapheurUi'

definePage({
  meta: {
    layout: 'default',
    action: 'read',
    subject: 'Workspace',
  },
})

const router = useRouter()
const loading = ref(true)
const filter = ref('all')
const items = ref<any[]>([])

async function load() {
  loading.value = true
  try {
    const res = await $api(`/workspace/shared-with-me?filter=${filter.value}`)
    items.value = res.data || res.items || res || []
  }
  finally {
    loading.value = false
  }
}

function openItem(item: any) {
  if (item.url) {
    router.push(item.url)
    return
  }
  if (item.kind === 'document' && item.document_id) {
    router.push({ name: 'espace-documents-id', params: { id: String(item.document_id) } })
    return
  }
  if (item.kind === 'folder' && item.workspace_id) {
    router.push({
      name: 'espace-dossiers',
      query: {
        workspace: String(item.workspace_id),
        ...(item.folder_id ? { folder: String(item.folder_id) } : {}),
      },
    })
    return
  }
  if (item.kind === 'workspace' && item.workspace_id) {
    router.push({ name: 'espace-collaboratifs-id', params: { id: String(item.workspace_id) } })
  }
}

function itemIcon(item: any) {
  if (item.kind === 'folder')
    return 'tabler-folder'
  if (item.kind === 'workspace')
    return 'tabler-users'
  return 'tabler-file'
}

function itemTitle(item: any) {
  return item.name || item.title || item.object || 'Élément partagé'
}

onMounted(load)
watch(filter, load)
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Partagés avec moi"
      subtitle="Documents, dossiers et espaces reçus de vos collègues"
      icon="tabler-share"
    />

    <VCard class="parapheur-section-card mb-4">
      <VCardText>
        <VBtnToggle
          v-model="filter"
          mandatory
          density="compact"
          divided
          color="primary"
        >
          <VBtn value="all">
            Tout
          </VBtn>
          <VBtn value="documents">
            Documents
          </VBtn>
          <VBtn value="folders">
            Dossiers
          </VBtn>
          <VBtn value="workspaces">
            Espaces
          </VBtn>
        </VBtnToggle>
      </VCardText>
    </VCard>

    <VCard class="parapheur-section-card">
      <VList v-if="items.length">
        <VListItem
          v-for="(item, idx) in items"
          :key="item.id || idx"
          :title="itemTitle(item)"
          :prepend-icon="itemIcon(item)"
          class="cursor-pointer"
          @click="openItem(item)"
        >
          <template #subtitle>
            <span>
              {{ item.kind === 'document' ? 'Document' : item.kind === 'folder' ? 'Dossier' : 'Espace' }}
              <template v-if="item.shared_by?.name">
                · par {{ item.shared_by.name }}
              </template>
              · {{ formatDateFr(item.created_at || item.shared_at) }}
              <template v-if="item.ability">
                · {{ labelOf(workspaceShareAbilityLabels, item.ability) }}
              </template>
            </span>
          </template>
          <template #append>
            <VIcon icon="tabler-chevron-right" />
          </template>
        </VListItem>
      </VList>
      <div
        v-else
        class="text-center py-10"
      >
        <VIcon
          icon="tabler-share-off"
          size="40"
          class="mb-2 text-medium-emphasis"
        />
        <div class="text-body-1 font-weight-medium mb-1">
          {{ loading ? 'Chargement…' : 'Aucun élément partagé' }}
        </div>
        <div
          v-if="!loading"
          class="text-caption text-medium-emphasis"
        >
          Les documents ou dossiers partagés avec vous apparaîtront ici.
        </div>
      </div>
    </VCard>
  </div>
</template>

<style scoped>
.cursor-pointer {
  cursor: pointer;
}
</style>
