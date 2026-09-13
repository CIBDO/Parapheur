<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { formatDateFr, labelOf, statusColor, statusLabels } from '@/utils/parapheurUi'

definePage({
  meta: { action: 'read', subject: 'Ged' },
})

const router = useRouter()
const loading = ref(true)
const items = ref<any[]>([])

onMounted(async () => {
  try {
    const res = await $api('/ged/recent', { query: { limit: 30 } })
    items.value = res.data || []
  }
  finally {
    loading.value = false
  }
})
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Récemment consultés"
      subtitle="Historique personnel filtré par vos droits d’accès actuels"
      icon="tabler-history"
    />
    <VCard
      class="parapheur-section-card"
      :loading="loading"
    >
      <VList>
        <VListItem
          v-for="doc in items"
          :key="doc.id"
          :title="doc.title || doc.object"
          :subtitle="`${doc.reference} · consulté le ${formatDateFr(doc.viewed_at)}`"
          @click="router.push({ name: 'ged-id', params: { id: String(doc.id) } })"
        >
          <template #append>
            <VChip
              size="small"
              :color="statusColor(doc.status)"
              label
            >
              {{ labelOf(statusLabels, doc.status) }}
            </VChip>
          </template>
        </VListItem>
        <VListItem v-if="!loading && !items.length">
          <VListItemTitle class="text-medium-emphasis">
            Aucune consultation récente.
          </VListItemTitle>
        </VListItem>
      </VList>
    </VCard>
  </div>
</template>
