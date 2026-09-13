<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { useGedDocumentList } from '@/composables/useGedDocumentList'

definePage({
  meta: { action: 'read', subject: 'Ged' },
})

const {
  loading, items, total, page, perPage, filters, headers, load, openDoc,
  labelOf, statusLabels, statusColor, confidentialityLabels, formatDateFr,
} = useGedDocumentList('mine')

filters.value.scope = 'mine'
watch([page, perPage], () => load())
onMounted(load)
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Mes documents"
      subtitle="Documents créés par moi"
      icon="tabler-folder-user"
    />
    <VCard class="parapheur-section-card">
      <VDataTableServer
        v-model:page="page"
        v-model:items-per-page="perPage"
        :headers="headers"
        :items="items"
        :items-length="total"
        :loading="loading"
        @update:options="({ page: p, itemsPerPage }: any) => { page = p; perPage = itemsPerPage }"
      >
        <template #item.object="{ item }">
          {{ item.title || item.object }}
        </template>
        <template #item.type="{ item }">
          {{ item.type?.name || '—' }}
        </template>
        <template #item.structure="{ item }">
          {{ item.structure?.code || '—' }}
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
        <template #item.confidentiality="{ item }">
          {{ labelOf(confidentialityLabels, item.confidentiality) }}
        </template>
        <template #item.document_date="{ item }">
          {{ formatDateFr(item.document_date) }}
        </template>
        <template #item.actions="{ item }">
          <VBtn
            size="small"
            variant="tonal"
            @click="openDoc(item.id)"
          >
            Ouvrir
          </VBtn>
        </template>
      </VDataTableServer>
    </VCard>
  </div>
</template>
