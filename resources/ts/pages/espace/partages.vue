<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { formatDateFr } from '@/utils/parapheurUi'

definePage({
  meta: {
    layout: 'default',
    action: 'read',
    subject: 'Workspace',
  },
})

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

onMounted(load)
watch(filter, load)
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Partagés avec moi"
      subtitle="Documents, dossiers et espaces reçus"
      icon="tabler-share"
    />
    <VCard class="parapheur-section-card mb-4">
      <VCardText>
        <VBtnToggle
          v-model="filter"
          mandatory
          density="compact"
          divided
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
          :title="item.name || item.title || item.object"
          :subtitle="`${item.kind || item.type || ''} — ${formatDateFr(item.created_at || item.shared_at)}`"
          :prepend-icon="item.kind === 'folder' ? 'tabler-folder' : 'tabler-file'"
        />
      </VList>
      <VCardText
        v-else
        class="text-medium-emphasis"
      >
        {{ loading ? 'Chargement…' : 'Aucun élément partagé.' }}
      </VCardText>
    </VCard>
  </div>
</template>
