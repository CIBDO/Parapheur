<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useCorrespondence } from '@/composables/useCorrespondence'
import { directionLabel, formatCorrespondenceNumber, statusLabel, correspondenceStatusColors, correspondencePriorityColors } from '@/utils/courrierUi'

definePage({
  meta: {
    layout: 'default',
    action: 'read',
    subject: 'Courrier',
  },
})

const { correspondences, fetchCorrespondences, loading } = useCorrespondence()
const search = ref('')
const status = ref<string | null>(null)

async function load() {
  await fetchCorrespondences({
    direction: 'entrant',
    search: search.value || undefined,
    status: status.value || undefined,
  })
}

onMounted(load)

const headers = [
  { title: 'N° arrivée', key: 'arrival_number' },
  { title: 'Objet', key: 'subject' },
  { title: 'Priorité', key: 'priority' },
  { title: 'Statut', key: 'status' },
  { title: 'Échéance', key: 'due_date' },
  { title: 'Reçu le', key: 'received_at' },
]
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Courriers entrants"
      subtitle="Registre d'arrivée"
    >
      <template #actions>
        <VBtn
          color="primary"
          prepend-icon="tabler-plus"
          :to="{ name: 'courrier-entrants-nouveau' }"
        >
          Nouveau
        </VBtn>
      </template>
    </ParapheurPageHeader>

    <VCard>
      <VCardText class="d-flex flex-wrap gap-3">
        <AppTextField
          v-model="search"
          label="Recherche"
          prepend-inner-icon="tabler-search"
          style="max-inline-size: 280px"
          @keyup.enter="load"
        />
        <VBtn
          color="primary"
          variant="tonal"
          @click="load"
        >
          Filtrer
        </VBtn>
      </VCardText>
      <VDataTable
        :headers="headers"
        :items="Array.isArray(correspondences) ? correspondences : (correspondences as any)?.data || []"
        :loading="loading"
        item-value="id"
      >
        <template #item.arrival_number="{ item }">
          <RouterLink :to="{ name: 'courrier-entrants-id', params: { id: item.id } }">
            {{ formatCorrespondenceNumber(item) }}
          </RouterLink>
        </template>
        <template #item.priority="{ item }">
          <VChip
            size="small"
            :color="correspondencePriorityColors[item.priority] || 'default'"
            variant="tonal"
          >
            {{ item.priority || '—' }}
          </VChip>
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
        <template #item.received_at="{ item }">
          {{ item.received_at ? new Date(item.received_at).toLocaleString() : '—' }}
        </template>
      </VDataTable>
    </VCard>
  </div>
</template>
