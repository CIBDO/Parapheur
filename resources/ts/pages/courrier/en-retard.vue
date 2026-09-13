<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useCorrespondence } from '@/composables/useCorrespondence'
import {
  correspondenceStatusColors,
  detailRouteName,
  formatCorrespondenceNumber,
  listItems,
  statusLabel,
} from '@/utils/courrierUi'

definePage({
  meta: { layout: 'default', action: 'read', subject: 'Courrier' },
})

const { correspondences, fetchCorrespondences, loading } = useCorrespondence()

onMounted(() => fetchCorrespondences({ overdue: 1, per_page: 50 }))

const items = computed(() => listItems(correspondences.value))
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Courriers en retard"
      subtitle="Échéances dépassées"
    />
    <VAlert
      type="warning"
      variant="tonal"
      class="mb-4"
    >
      {{ items.length }} courrier(s) hors délai
    </VAlert>
    <VCard>
      <VDataTable
        :headers="[
          { title: 'N°', key: 'number' },
          { title: 'Objet', key: 'subject' },
          { title: 'Échéance', key: 'due_date' },
          { title: 'Statut', key: 'status' },
        ]"
        :items="items"
        :loading="loading"
      >
        <template #item.number="{ item }">
          <RouterLink :to="{ name: detailRouteName(item.direction), params: { id: item.id } }">
            {{ formatCorrespondenceNumber(item) }}
          </RouterLink>
        </template>
        <template #item.status="{ item }">
          <VChip
            size="small"
            :color="correspondenceStatusColors[item.status] || 'default'"
            variant="tonal"
          >
            {{ statusLabel(item.status) }}
          </VChip>
        </template>
      </VDataTable>
    </VCard>
  </div>
</template>
