<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { useGedDocumentList } from '@/composables/useGedDocumentList'

definePage({
  name: 'ged-a-traiter',
  meta: { action: 'read', subject: 'Ged' },
})

const {
  loading, items, total, page, perPage, headers, load, openDoc,
  labelOf, statusLabels, statusColor, confidentialityLabels, formatDateFr,
} = useGedDocumentList('to_process')

watch([page, perPage], () => load())
onMounted(load)
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="À traiter"
      subtitle="Documents qui m’ont été assignés"
      icon="tabler-inbox"
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
            :to="{ name: 'parapheur-id', params: { id: String(item.id) } }"
          >
            Traiter
          </VBtn>
          <VBtn
            size="small"
            variant="text"
            @click="openDoc(item.id)"
          >
            GED
          </VBtn>
        </template>
      </VDataTableServer>
    </VCard>
  </div>
</template>
