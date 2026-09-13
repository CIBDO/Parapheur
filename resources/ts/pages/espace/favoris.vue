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
const items = ref<any[]>([])

const kindLabel: Record<string, string> = {
  document: 'Document',
  folder: 'Dossier',
  reference: 'Référence',
}

async function load() {
  loading.value = true
  try {
    const res = await $api('/workspace/favorites')
    items.value = res.data || []
  }
  finally {
    loading.value = false
  }
}

async function remove(item: any) {
  await $api('/workspace/favorites/toggle', {
    method: 'POST',
    body: { type: item.kind, id: item.favoritable_id },
  })
  await load()
}

onMounted(load)
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Mes favoris"
      subtitle="Documents, dossiers et références épinglés"
      icon="tabler-star"
    />
    <VCard class="parapheur-section-card">
      <VList v-if="items.length">
        <VListItem
          v-for="item in items"
          :key="item.id"
          :title="item.title || item.name"
          :subtitle="`${kindLabel[item.kind] || item.kind} · ${formatDateFr(item.created_at)}`"
          prepend-icon="tabler-star"
          :to="item.route || undefined"
        >
          <template #append>
            <VBtn
              size="small"
              variant="text"
              color="warning"
              @click.prevent="remove(item)"
            >
              Retirer
            </VBtn>
          </template>
        </VListItem>
      </VList>
      <VCardText
        v-else
        class="text-medium-emphasis"
      >
        {{ loading ? 'Chargement…' : 'Aucun favori. Épinglez un document depuis sa fiche.' }}
      </VCardText>
    </VCard>
  </div>
</template>
