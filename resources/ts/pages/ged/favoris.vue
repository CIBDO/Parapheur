<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { formatDateFr, labelOf, statusColor, statusLabels } from '@/utils/gedUi'

definePage({
  meta: { action: 'read', subject: 'Ged' },
})

const router = useRouter()
const loading = ref(true)
const items = ref<any[]>([])
const total = ref(0)
const page = ref(1)

const load = async () => {
  loading.value = true
  try {
    const res = await $api('/ged/favorites', { query: { page: page.value, per_page: 15 } })
    items.value = res.data || []
    total.value = res.total || 0
  }
  finally {
    loading.value = false
  }
}

watch(page, load)
onMounted(load)
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Mes favoris"
      subtitle="Documents épinglés — accessibles uniquement si vos droits le permettent encore"
      icon="tabler-star"
    />
    <VCard class="parapheur-section-card">
      <VDataTable
        :headers="[
          { title: 'Référence', key: 'reference' },
          { title: 'Objet', key: 'object' },
          { title: 'Statut', key: 'status' },
          { title: 'Date', key: 'document_date' },
          { title: '', key: 'actions' },
        ]"
        :items="items"
        :loading="loading"
        :items-per-page="15"
      >
        <template #item.object="{ item }">
          {{ item.title || item.object }}
        </template>
        <template #item.status="{ item }">
          <VChip
            size="small"
            :color="statusColor(item.status)"
            label
          >
            {{ labelOf(statusLabels, item.status) }}
          </VChip>
        </template>
        <template #item.document_date="{ item }">
          {{ formatDateFr(item.document_date) }}
        </template>
        <template #item.actions="{ item }">
          <VBtn
            size="small"
            variant="tonal"
            @click="router.push({ name: 'ged-id', params: { id: String(item.id) } })"
          >
            Ouvrir
          </VBtn>
        </template>
        <template #no-data>
          <div class="text-center py-8 text-medium-emphasis">
            Aucun favori.
          </div>
        </template>
      </VDataTable>
    </VCard>
  </div>
</template>
